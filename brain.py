# ai_service.py
from flask import Flask, request, jsonify
import pandas as pd
import joblib
import os
import time
from flask_cors import CORS
from datetime import datetime

app = Flask(__name__)
CORS(app)

MODEL_FILE = 'asset_failure_model.pkl'

# -----------------------------
# GLOBAL STORAGE
# -----------------------------
ASSETS_CACHE = []  # store assets sent by PHP
ANOMALY_QUEUE = []
LAST_ANOMALY_TIME = 0
COOLDOWN = 60  # seconds

# -----------------------------
# LOAD MODEL
# -----------------------------
if os.path.exists(MODEL_FILE):
    model = joblib.load(MODEL_FILE)
else:
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
        cause = (
            f"High risk! Asset may fail soon ({failure_prob:.1%}). "
            f"It has {d_count} damaged components, "
            f"including: {', '.join(components_dict.keys()) if components_dict else 'none'}. "
            f"Consider urgent maintenance or replacement."
        )
    elif failure_prob > 0.5:
        cause = (
            f"Moderate risk ({failure_prob:.1%}). "
            f"Asset has some damages ({d_count} components) and is {age_days} days old. "
            f"Monitor usage and repair components as needed."
        )
    else:
        cause = (
            f"Low risk ({failure_prob:.1%}). "
            f"Asset is currently stable, with minor or no damages. Good for continued use."
        )

    return cause, failure_prob

# -----------------------------
# REFRESH ANOMALY QUEUE
# -----------------------------
def refresh_anomaly_queue():
    global ANOMALY_QUEUE
    ANOMALY_QUEUE = []

    for asset in ASSETS_CACHE:
        try:
            date_added = datetime.fromisoformat(asset.get('date_added', datetime.now().isoformat()))
        except Exception:
            date_added = datetime.now()
        age_days = (datetime.now() - date_added).days

        # Assume asset can have a "damaged_components" list or "d_count"
        d_count = asset.get('d_count', 0)
        components_dict = asset.get('components_dict', {})

        thoughts, failure_prob = analyze_asset(asset['asset_id'], d_count, age_days, components_dict)

        if d_count >= 3 or "High risk" in thoughts:
            ANOMALY_QUEUE.append({
                "db_id": asset['asset_id'],
                "asset_id": asset['asset_id'],
                "d_count": d_count,
                "failure_prob": failure_prob,
                "thoughts": thoughts
            })

# -----------------------------
# ENDPOINT: Receive Assets from PHP
# -----------------------------
@app.route('/receive_assets', methods=['POST'])
def receive_assets():
    global ASSETS_CACHE
    try:
        data = request.get_json()
        if not data:
            return jsonify({"status": "error", "message": "No data received"}), 400
        ASSETS_CACHE = data
        return jsonify({"status": "success", "count": len(data)})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

# -----------------------------
# ENDPOINT: Scan Assets / Bubble Notification
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
                "summary": f"Asset has {anomaly['d_count']} damage reports.",
                "thoughts": anomaly['thoughts']
            }
    else:
        response['messages'].append("Velyn system active. Neural links established.")

    return jsonify(response)

# -----------------------------
# ENDPOINT: All Anomalies
# -----------------------------
@app.route('/all_anomalies', methods=['GET'])
def all_anomalies():
    if not ANOMALY_QUEUE:
        refresh_anomaly_queue()

    result = [
        {
            "db_id": a['db_id'],
            "asset_id": a['asset_id'],
            "damage_count": a.get('d_count', 0),
            "failure_prob": a.get('failure_prob', 0.0),
            "thoughts": a.get('thoughts', "")
        } for a in ANOMALY_QUEUE
    ]
    return jsonify({"anomalies": result, "count": len(result)})

# -----------------------------
# RUN APP
# -----------------------------
if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    app.run(host='0.0.0.0', port=port)
