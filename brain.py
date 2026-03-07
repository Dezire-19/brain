import requests
import pandas as pd
import joblib
import os
import time
from flask import Flask, request, jsonify
from flask_cors import CORS
from datetime import datetime

application = Flask(__name__)
CORS(app)

MODEL_FILE = 'asset_failure_model.pkl'
PHP_URL = "https://velynasset.infinityfree.me/assets.php"

headers = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/145.0.0.0 Safari/537.36"
}

try:
    response = requests.get(f"{PHP_URL}?action=get_assets", headers=headers, timeout=10)
    response.raise_for_status()  # Raises error if status != 200
    assets = response.json()
    print(assets[:5])
except requests.exceptions.RequestException as e:
    print("Request failed:", e)

# Load ML Model
if os.path.exists(MODEL_FILE):
    model = joblib.load(MODEL_FILE)
else:
    raise FileNotFoundError(f"Model file '{MODEL_FILE}' not found.")

# Global State
ANOMALY_QUEUE = []
LAST_ANOMALY_TIME = 0
COOLDOWN = 60 

def analyze_asset(asset_id, d_count, age_days, components_dict):
    MODEL_FEATURES = ['d_count', 'age_days', 'environment_score', 'mishandling_score',
                      'screen_failures', 'battery_failures', 'keyboard_failures', 'motherboard_failures']
    
    input_data = {'d_count': d_count, 'age_days': age_days, 'environment_score': 5, 'mishandling_score': 0}
    for c, count in components_dict.items():
        col_name = f"{c.lower().replace(' ', '_')}_failures"
        input_data[col_name] = count

    features = pd.DataFrame([input_data]).reindex(columns=MODEL_FEATURES, fill_value=0)
    failure_prob = model.predict_proba(features)[0][1]

    if failure_prob > 0.8:
        thoughts = f"High risk! ({failure_prob:.1%}). Urgent maintenance recommended."
    elif failure_prob > 0.5:
        thoughts = f"Moderate risk ({failure_prob:.1%}). Monitor components and age."
    else:
        thoughts = f"Low risk ({failure_prob:.1%}). Asset stable."
    
    return thoughts, failure_prob

def refresh_anomaly_queue():
    global ANOMALY_QUEUE
    try:
        response = requests.get(f"{PHP_URL}?action=get_assets")
        assets = response.json()
        
        updated_queue = []
        for asset in assets:
            # Parse Date/Age
            date_val = asset['date_acquired']
            dt = datetime.strptime(date_val, '%Y-%m-%d') if date_val else datetime.now()
            age_days = (datetime.now() - dt).days
            
            # Count Damages
            comp_raw = asset['components'] or ""
            comp_list = [c.strip() for c in comp_raw.split(',') if c.strip()]
            d_count = len(comp_list)
            
            comp_dict = {}
            for c in comp_list: comp_dict[c] = comp_dict.get(c, 0) + 1
            
            thoughts, prob = analyze_asset(asset['asset_id'], d_count, age_days, comp_dict)

            # Filter for Anomaly Queue
            if d_count >= 3 or "High risk" in thoughts:
                anomaly_obj = {
                    "db_id": asset['db_id'],
                    "asset_id": asset['asset_id'],
                    "d_count": d_count,
                    "failure_prob": prob,
                    "thoughts": thoughts,
                    "date_acquired": date_val
                }
                updated_queue.append(anomaly_obj)
                

        
        ANOMALY_QUEUE = updated_queue
    except Exception as e:
        print(f"Sync Error: {e}")

# -----------------------------
# ENDPOINT: NOTIFICATION SCAN
# -----------------------------
@app.route('/scan', methods=['GET'])
def scan_assets():
    global LAST_ANOMALY_TIME, ANOMALY_QUEUE
    current_time = int(time.time())
    
    if not ANOMALY_QUEUE:
        refresh_anomaly_queue()

    # Pop the first anomaly if cooldown has passed
    if ANOMALY_QUEUE and (current_time - LAST_ANOMALY_TIME >= COOLDOWN):
        anomaly = ANOMALY_QUEUE.pop(0)
        LAST_ANOMALY_TIME = current_time
        return jsonify({
            "critical": True, 
            "anomaly": {
                "id": anomaly['db_id'],
                "asset_id": anomaly['asset_id'],
                "damage_count": anomaly['d_count'],
                "failure_prob": anomaly['failure_prob'],
                "summary": f"Asset has {anomaly['d_count']} damages.",
                "thoughts": anomaly['thoughts']
            }
        })
    
    return jsonify({"messages": ["Velyn monitoring active."], "critical": False})

# -----------------------------
# ENDPOINT: ALL ASSET SCAN
# -----------------------------
@app.route('/all_anomalies', methods=['GET'])
def all_anomalies():
    refresh_anomaly_queue() # Refresh to get latest data
    return jsonify({
        "anomalies": ANOMALY_QUEUE, 
        "count": len(ANOMALY_QUEUE)
    })

if __name__ == '__main__':
    app.run(port=5000, debug=True)




