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

    # Extract component specific failures if they exist
    for c, count in components_dict.items():
        col_name = c.lower().replace(" ", "_") + "_failures"
        if col_name in MODEL_FEATURES:
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
# NEW: DATA RECEIVER (Plan B - Push from PHP)
# -----------------------------
@app.route('/receive_data', methods=['POST'])
def receive_data():
    global ANOMALY_QUEUE
    data = request.json
    
    if not data or 'assets' not in data:
        return jsonify({"status": "error", "message": "No data provided"}), 400
    
    received_assets = data['assets']
    ANOMALY_QUEUE = [] # Reset queue with fresh data from PHP

    for asset in received_assets:
        asset_id = asset.get('asset_id')
        d_count = int(asset.get('d_count', 0))
        age_days = float(asset.get('age_days', 0))
        
        # Analyze using the loaded model
        thoughts, failure_prob = analyze_asset(asset_id, d_count, age_days)

        # If it's damaged at all, put it in the anomaly list
        if d_count >= 1:
            ANOMALY_QUEUE.append({
                "db_id": asset_id,
                "asset_id": asset_id,
                "d_count": d_count,
                "failure_prob": failure_prob,
                "thoughts": thoughts
            })
    
    print(f"PUSH_SYNC: Received {len(received_assets)} assets. Found {len(ANOMALY_QUEUE)} anomalies.")
    return jsonify({"status": "success", "anomalies_found": len(ANOMALY_QUEUE)})

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
        # Send one anomaly at a time for the notification bubble
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
    # Return everything currently in the queue
    result = []
    for a in ANOMALY_QUEUE:
        result.append({
            "db_id": a['db_id'],
            "asset_id": a['asset_id'],
            "damage_count": a.get('d_count', 0),
            "failure_prob": a.get('failure_prob', 0.0),
            "thoughts": a.get('thoughts', "")
        })
    
    print(f"FRONTEND: Sending {len(result)} anomalies.")
    return jsonify({"anomalies": result, "count": len(result)})

@app.route('/')
def home():
    return jsonify({
        "status": "online",
        "message": "Velyn AI Neural Link Established",
        "endpoints": ["/scan", "/all_anomalies", "/receive_data"]
    })

# -----------------------------
# RUN APP
# -----------------------------
if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    app.run(host='0.0.0.0', port=port)

# Gunicorn compatibility
application = app
