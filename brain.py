from flask import Flask, request, jsonify
import pandas as pd
import joblib
import os
import time
from flask_cors import CORS
from datetime import datetime
import requests

app = Flask(__name__)
CORS(app)

MODEL_FILE = 'asset_failure_model.pkl'
PHP_API_BASE = "https://velynasset.infinityfree.me/assets.php"

# -----------------------------
# GLOBAL STORAGE
# -----------------------------
ANOMALY_QUEUE = []
LAST_ANOMALY_TIME = 0
COOLDOWN = 60  # seconds

# -----------------------------
# LOAD MODEL
# -----------------------------
if os.path.exists(MODEL_FILE):
    model = joblib.load(MODEL_FILE)
    print("AI MODEL: Loaded successfully.")
else:
    print(f"AI MODEL: {MODEL_FILE} not found!")
    raise FileNotFoundError(f"Model file '{MODEL_FILE}' not found.")

# -----------------------------
# HELPER: ANALYZE ASSET
# -----------------------------
def analyze_asset(asset_id, d_count, age_days, components_dict=None, environment_score=5):
    if components_dict is None:
        components_dict = {}

    MODEL_FEATURES = [
        'd_count', 'age_days', 'environment_score', 'mishandling_score',
        'screen_failures', 'battery_failures', 'keyboard_failures', 'motherboard_failures'
    ]

    input_data = {
        'd_count': d_count,
        'age_days': age_days,
        'environment_score': environment_score,
        'mishandling_score': 0
    }

    for c, count in components_dict.items():
        col_name = c.lower().replace(" ", "_") + "_failures"
        input_data[col_name] = count

    features = pd.DataFrame([input_data]).reindex(columns=MODEL_FEATURES, fill_value=0)
    failure_prob = model.predict_proba(features)[0][1]

    if failure_prob > 0.8:
        cause = f"High risk! ({failure_prob:.1%}). Urgent maintenance required."
    elif failure_prob > 0.5:
        cause = f"Moderate risk ({failure_prob:.1%}). Monitor asset usage."
    else:
        cause = f"Low risk ({failure_prob:.1%}). Asset stable."

    return cause, failure_prob

# -----------------------------
# HELPER: FETCH DATA (With Browser Mimicry)
# -----------------------------
def fetch_assets():
    # Headers help bypass InfinityFree security walls
    headers = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"}
    try:
        response = requests.get(f"{PHP_API_BASE}?action=all", headers=headers, timeout=10)
        response.raise_for_status()
        data = response.json()
        print(f"PHP_FETCH: Received {len(data)} assets.")
        return data
    except Exception as e:
        print(f"PHP_FETCH_ERROR (Assets): {e}")
        return []

def fetch_damaged_components(asset_id):
    headers = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"}
    try:
        response = requests.get(f"{PHP_API_BASE}?action=damaged", headers=headers, timeout=10)
        response.raise_for_status()
        all_damaged = response.json()
        comp_dict = {}
        d_count = 0
        for item in all_damaged:
            # Match IDs safely as strings
            if str(item['asset_id']) != str(asset_id):
                continue
            comps = [c.strip() for c in item['component'].split(',') if c.strip()]
            for c in comps:
                comp_dict[c] = comp_dict.get(c, 0) + 1
                d_count += 1
        return d_count, comp_dict
    except Exception as e:
        print(f"PHP_FETCH_ERROR (Damaged): {e}")
        return 0, {}

# -----------------------------
# REFRESH ANOMALY QUEUE
# -----------------------------
def refresh_anomaly_queue():
    global ANOMALY_QUEUE
    print("DEBUG: Refreshing Anomaly Queue...")
    assets = fetch_assets()
    ANOMALY_QUEUE = []

    for asset in assets:
        try:
            date_added = datetime.fromisoformat(asset['date_added'])
        except Exception:
            date_added = datetime.now()
        
        age_days = (datetime.now() - date_added).days
        d_count, components_dict = fetch_damaged_components(asset['asset_id'])
        thoughts, failure_prob = analyze_asset(asset['asset_id'], d_count, age_days, components_dict)

        # UPDATED: Threshold lowered to 1 for easier testing
        if d_count >= 1 or failure_prob > 0.5:
            print(f"DEBUG: Found Anomaly in Asset {asset['asset_id']} (Damage: {d_count})")
            ANOMALY_QUEUE.append({
                "db_id": asset['asset_id'],
                "asset_id": asset['asset_id'],
                "d_count": d_count,
                "failure_prob": failure_prob,
                "thoughts": thoughts
            })
    
    print(f"DEBUG: Refresh complete. {len(ANOMALY_QUEUE)} anomalies queued.")

# -----------------------------
# ENDPOINTS
# -----------------------------
@app.route('/scan', methods=['GET'])
def scan_assets():
    global LAST_ANOMALY_TIME, ANOMALY_QUEUE
    type_ = request.args.get('type', 'greeting')
    response = {"messages": [], "critical": False, "anomaly": None}
    current_time = int(time.time())

    if type_ == 'standby':
        if not ANOMALY_QUEUE:
            refresh_anomaly_queue()

        if ANOMALY_QUEUE and current_time - LAST_ANOMALY_TIME >= COOLDOWN:
            anomaly = ANOMALY_QUEUE.pop(0)
            LAST_ANOMALY_TIME = current_time
            response['critical'] = True
            response['anomaly'] = {
                "id": anomaly['db_id'],
                "asset_id": anomaly['asset_id'],
                "damage_count": anomaly['d_count'],
                "failure_prob": anomaly['failure_prob'],
                "summary": f"Detected {anomaly['d_count']} issues.",
                "thoughts": anomaly['thoughts']
            }
    else:
        response['messages'].append("Velyn system active. Neural links established.")

    return jsonify(response)

@app.route('/all_anomalies', methods=['GET'])
def all_anomalies():
    refresh_anomaly_queue()
    result = []
    for a in ANOMALY_QUEUE:
        result.append({
            "db_id": a['db_id'],
            "asset_id": a['asset_id'],
            "damage_count": a.get('d_count', 0),
            "failure_prob": a.get('failure_prob', 0.0),
            "thoughts": a.get('thoughts', "")
        })
    
    print(f"SERVER: Sending {len(result)} anomalies to frontend.")
    return jsonify({"anomalies": result, "count": len(result)})

@app.route('/')
def home():
    return jsonify({
        "status": "online",
        "message": "Velyn AI Neural Link Established",
        "endpoints": ["/scan", "/all_anomalies"]
    })

# -----------------------------
# RUN APP
# -----------------------------
if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    app.run(host='0.0.0.0', port=port)

application = app
