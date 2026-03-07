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
# GLOBAL STORAGE (Stays in memory)
# -----------------------------
ANOMALY_QUEUE = []
ALL_HISTORY = [] # For the /all_anomalies feature
LAST_ANOMALY_TIME = 0
COOLDOWN = 60  

# Load AI Model
if os.path.exists(MODEL_FILE):
    model = joblib.load(MODEL_FILE)
else:
    raise FileNotFoundError(f"Model file '{MODEL_FILE}' not found.")

# -----------------------------
# AI CALCULATION LOGIC
# -----------------------------
def analyze_asset(d_count, age_days, components_list):
    MODEL_FEATURES = [
        'd_count', 'age_days', 'environment_score', 'mishandling_score',
        'screen_failures', 'battery_failures', 'keyboard_failures', 'motherboard_failures'
    ]
    input_data = {'d_count': d_count, 'age_days': age_days, 'environment_score': 5, 'mishandling_score': 0}
    
    for c in components_list:
        col_name = c.lower().replace(" ", "_") + "_failures"
        if col_name in MODEL_FEATURES:
            input_data[col_name] = 1

    features = pd.DataFrame([input_data]).reindex(columns=MODEL_FEATURES, fill_value=0)
    failure_prob = model.predict_proba(features)[0][1]

    if failure_prob > 0.8:
        thoughts = f"High risk! ({failure_prob:.1%}). Urgent maintenance suggested."
    elif failure_prob > 0.5:
        thoughts = f"Moderate risk ({failure_prob:.1%}). Monitor closely."
    else:
        thoughts = f"Low risk ({failure_prob:.1%}). Asset is stable."
        
    return thoughts, failure_prob

# -----------------------------
# NEW: PHP PUSHES DATA HERE
# -----------------------------
@app.route('/push_data', methods=['POST'])
def receive_from_php():
    global ANOMALY_QUEUE, ALL_HISTORY
    data = request.json
    
    # Process the data immediately
    thoughts, prob = analyze_asset(
        int(data['d_count']), 
        int(data['age_days']), 
        data.get('components', [])
    )

    entry = {
        "db_id": data.get('id'),
        "asset_id": data['asset_id'],
        "damage_count": data['d_count'],
        "failure_prob": prob,
        "thoughts": thoughts,
        "date_acquired": data.get('date_acquired', str(datetime.now().date())),
        "timestamp": time.time()
    }

    # Add to history list
    ALL_HISTORY.append(entry)

    # If it's a high risk, add to the notification queue
    if prob > 0.5 or int(data['d_count']) >= 3:
        ANOMALY_QUEUE.append(entry)

    return jsonify({"status": "received", "risk": thoughts})

# -----------------------------
# KEPT: SCAN / NOTIFICATION BUBBLE
# -----------------------------
@app.route('/scan', methods=['GET'])
def scan_assets():
    global LAST_ANOMALY_TIME, ANOMALY_QUEUE
    current_time = int(time.time())
    response = {"messages": [], "critical": False, "anomaly": None}

    if ANOMALY_QUEUE and (current_time - LAST_ANOMALY_TIME >= COOLDOWN):
        anomaly = ANOMALY_QUEUE.pop(0)
        LAST_ANOMALY_TIME = current_time
        response['critical'] = True
        response['anomaly'] = anomaly
    else:
        response['messages'].append("Velyn system scanning... No new critical anomalies.")

    return jsonify(response)

# -----------------------------
# KEPT: ALL ANOMALIES FEATURE
# -----------------------------
@app.route('/all_anomalies', methods=['GET'])
def all_anomalies():
    return jsonify({"anomalies": ALL_HISTORY, "count": len(ALL_HISTORY)})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
