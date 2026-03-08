from flask import Flask, request, jsonify
import pandas as pd
import joblib
import os
import time
import requests
from flask_cors import CORS
from datetime import datetime

app = Flask(__name__)
CORS(app)

# --- SETTINGS ---
MODEL_FILE = 'asset_failure_model.pkl'
# REPLACE WITH YOUR ACTUAL INFINITYFREE URL
PHP_URL = "https://velynasset.infinityfree.me/api.php" 

ANOMALY_QUEUE = []
LAST_ANOMALY_TIME = 0
COOLDOWN = 60 

# --- LOAD MODEL ---
if os.path.exists(MODEL_FILE):
    model = joblib.load(MODEL_FILE)
else:
    raise FileNotFoundError(f"Model file '{MODEL_FILE}' not found.")

# --- HELPERS ---
def call_db(action, asset_id=None, method='GET', data=None):
    params = {'action': action}
    if asset_id: params['asset_id'] = asset_id
    if method == 'POST':
        return requests.post(PHP_URL, params=params, json=data).json()
    return requests.get(PHP_URL, params=params).json()

def analyze_asset(asset_id, d_count, age_days, components_dict=None):
    if components_dict is None: components_dict = {}
    MODEL_FEATURES = ['d_count', 'age_days', 'environment_score', 'mishandling_score',
                      'screen_failures', 'battery_failures', 'keyboard_failures', 'motherboard_failures']
    input_data = {'d_count': d_count, 'age_days': age_days, 'environment_score': 5, 'mishandling_score': 0}
    for c, count in components_dict.items():
        col_name = c.lower().replace(" ", "_") + "_failures"
        input_data[col_name] = count
    features = pd.DataFrame([input_data]).reindex(columns=MODEL_FEATURES, fill_value=0)
    failure_prob = model.predict_proba(features)[0][1]
    
    if failure_prob > 0.8: cause = f"High risk! ({failure_prob:.1%}). Urgent maintenance needed."
    elif failure_prob > 0.5: cause = f"Moderate risk ({failure_prob:.1%}). Monitor closely."
    else: cause = f"Low risk ({failure_prob:.1%}). Stable."
    return cause, failure_prob

def asset_exists(asset_id):
    res = call_db('check_exists', asset_id)
    return len(res) > 0

def refresh_anomaly_queue():
    global ANOMALY_QUEUE
    assets = call_db('get_assets')
    ANOMALY_QUEUE = []
    for row in assets:
        # Age Calc
        dt = row['date_acquired']
        age_days = (datetime.now() - datetime.strptime(dt, '%Y-%m-%d')).days if dt else 0
        # Damaged Components
        comp_rows = call_db('get_damaged', row['asset_id'])
        components_dict = {}
        d_count = 0
        for r in comp_rows:
            comps = [c.strip() for c in r['component'].split(',') if c.strip()]
            for c in comps:
                components_dict[c] = components_dict.get(c, 0) + 1
                d_count += 1
        
        thoughts, prob = analyze_asset(row['asset_id'], d_count, age_days, components_dict)
        if d_count >= 3 or "High risk" in thoughts:
            row.update({'thoughts': thoughts, 'd_count': d_count, 'failure_prob': prob})
            ANOMALY_QUEUE.append(row)
            call_db('save_history', method='POST', data={
                'asset_id': row['asset_id'], 'failure_prob': prob, 
                'd_count': d_count, 'age_days': age_days, 'components': ", ".join(components_dict.keys())
            })

# --- ROUTES (Original Features) ---
@app.route('/scan', methods=['GET'])
def scan_assets():
    global LAST_ANOMALY_TIME, ANOMALY_QUEUE
    type_ = request.args.get('type', 'greeting')
    current_time = int(time.time())
    if type_ == 'standby':
        if not ANOMALY_QUEUE: refresh_anomaly_queue()
        ANOMALY_QUEUE = [a for a in ANOMALY_QUEUE if asset_exists(a['asset_id'])]
        if ANOMALY_QUEUE and current_time - LAST_ANOMALY_TIME >= COOLDOWN:
            anomaly = ANOMALY_QUEUE.pop(0)
            LAST_ANOMALY_TIME = current_time
            return jsonify({"critical": True, "anomaly": anomaly})
    return jsonify({"messages": ["Velyn system active."], "critical": False})

@app.route('/all_anomalies', methods=['GET'])
def all_anomalies():
    if not ANOMALY_QUEUE: refresh_anomaly_queue()
    return jsonify({"anomalies": ANOMALY_QUEUE, "count": len(ANOMALY_QUEUE)})

@app.route('/failure_by_month', methods=['GET'])
def failure_by_month():
    asset_id = request.args.get('asset_id')
    return jsonify({"monthly_failure_rates": call_db('failure_history', asset_id), "asset_id": asset_id})

@app.route('/repaired_by_month', methods=['GET'])
def repaired_by_month():
    asset_id = request.args.get('asset_id')
    return jsonify({"monthly_repairs": call_db('repairs', asset_id), "asset_id": asset_id})

@app.route('/maintenance_by_month', methods=['GET'])
def maintenance_by_month():
    asset_id = request.args.get('asset_id')
    return jsonify({"monthly_maintenance": call_db('maintenance', asset_id), "asset_id": asset_id})

@app.route('/last_maintenance', methods=['GET'])
def last_maintenance():
    asset_id = request.args.get('asset_id')
    res = call_db('last_maint', asset_id)
    return jsonify({"last_maintenance": res[0]['last_maintenance'] if res else None})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=int(os.environ.get("PORT", 5000)))
