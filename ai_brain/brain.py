# ai_service.py
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
# HELPER: FETCH DATA FROM PHP API
# -----------------------------
def fetch_assets():
    try:
        response = requests.get(f"{PHP_API_BASE}?action=all")
        response.raise_for_status()
        return response.json()
    except Exception as e:
        print(f"Error fetching assets: {e}")
        return []

def fetch_damaged_components(asset_id):
    try:
        response = requests.get(f"{PHP_API_BASE}?action=damaged")
        response.raise_for_status()
        all_damaged = response.json()
        comp_dict = {}
        d_count = 0
        for item in all_damaged:
            if item['asset_id'] != asset_id:
                continue
            comps = [c.strip() for c in item['component'].split(',') if c.strip()]
            for c in comps:
                comp_dict[c] = comp_dict.get(c, 0) + 1
                d_count += 1
        return d_count, comp_dict
    except Exception as e:
        print(f"Error fetching damaged components: {e}")
        return 0, {}

# -----------------------------
# REFRESH ANOMALY QUEUE
# -----------------------------
def refresh_anomaly_queue():
    global ANOMALY_QUEUE
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

        if d_count >= 3 or "High risk" in thoughts:
            ANOMALY_QUEUE.append({
                "db_id": asset['asset_id'],
                "asset_id": asset['asset_id'],
                "d_count": d_count,
                "failure_prob": failure_prob,
                "thoughts": thoughts
            })

# -----------------------------
# SCAN / BUBBLE NOTIFICATION
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
# ALL ANOMALIES ENDPOINT
# -----------------------------
@app.route('/all_anomalies', methods=['GET'])
def all_anomalies():
    if not ANOMALY_QUEUE:
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

    return jsonify({"anomalies": result, "count": len(result)})




# At the end of brain.py
application = app  # Render expects 'application', not 'app'

