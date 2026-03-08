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
PHP_URL = "https://velynasset.infinityfree.me/api.php" 

ANOMALY_QUEUE = []
LAST_ANOMALY_TIME = 0
COOLDOWN = 60  # seconds

# --- LOAD MODEL ---
if os.path.exists(MODEL_FILE):
    model = joblib.load(MODEL_FILE)
else:
    raise FileNotFoundError(f"Model file '{MODEL_FILE}' not found.")

# --- THE PHP BRIDGE HELPER ---
def call_db(action, asset_id=None, method='GET', data=None):
    """Unified helper to talk to InfinityFree with Browser-like headers"""
    params = {'action': action}
    if asset_id: params['asset_id'] = asset_id
    
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36"
    }

    try:
        if method == 'POST':
            response = requests.post(PHP_URL, params=params, json=data, headers=headers, timeout=10)
        else:
            response = requests.get(PHP_URL, params=params, headers=headers, timeout=10)
        
        if response.status_code == 200:
            return response.json()
        return []
    except Exception as e:
        print(f"Bridge Connection Error ({action}): {e}")
        return []

# --- HELPER: ANALYZE ASSET ---
def analyze_asset(asset_id, d_count, age_days, components_dict=None, environment_score=5):
    if components_dict is None: components_dict = {}
    MODEL_FEATURES = ['d_count', 'age_days', 'environment_score', 'mishandling_score',
                      'screen_failures', 'battery_failures', 'keyboard_failures', 'motherboard_failures']
    
    input_data = {'d_count': d_count, 'age_days': age_days, 'environment_score': environment_score, 'mishandling_score': 0}
    for c, count in components_dict.items():
        col_name = c.lower().replace(" ", "_") + "_failures"
        input_data[col_name] = count
        
    features = pd.DataFrame([input_data]).reindex(columns=MODEL_FEATURES, fill_value=0)
    failure_prob = model.predict_proba(features)[0][1]
    
    if failure_prob > 0.8:
        cause = f"High risk! Asset may fail soon ({failure_prob:.1%}). It has {d_count} damaged components. Consider urgent maintenance."
    elif failure_prob > 0.5:
        cause = f"Moderate risk ({failure_prob:.1%}). Asset has {d_count} components damaged and is {age_days} days old."
    else:
        cause = f"Low risk ({failure_prob:.1%}). Asset is currently stable."
        
    return cause, failure_prob

# --- REFRESH ANOMALY QUEUE ---
def refresh_anomaly_queue():
    global ANOMALY_QUEUE
    # 1. Get all assets
    assets = call_db('get_assets')
    ANOMALY_QUEUE = []
    
    if not assets:
        print("No assets found from PHP bridge.")
        return

    for row in assets:
        # Clean the asset_id (removes leading/trailing spaces)
        current_asset_id = row.get('asset_id', '').strip()
        
        # 2. Calculate Age
        dt_str = row.get('date_acquired')
        try:
            dt_obj = datetime.strptime(dt_str, '%Y-%m-%d')
            age_days = (datetime.now() - dt_obj).days
        except:
            age_days = 0

        # 3. Get damaged components - Use the cleaned asset_id
        comp_rows = call_db('get_damaged', current_asset_id)
        components_dict = {}
        total_d_count = 0
        
        for r in comp_rows:
            # Your SQL dump shows: "Cooling fan, Mother Board, Ports"
            # We split by comma and count each one
            raw_component_str = r.get('component', '')
            if raw_component_str:
                comps = [c.strip() for c in raw_component_str.split(',') if c.strip()]
                for c in comps:
                    # Map to the specific columns the model expects
                    key = c.lower().replace(" ", "_")
                    components_dict[key] = components_dict.get(key, 0) + 1
                    total_d_count += 1
        
        # 4. AI Analysis
        thoughts, prob = analyze_asset(current_asset_id, total_d_count, age_days, components_dict)
        
        # Based on your SQL: 'HSNP - 5' has 8+ components. It SHOULD trigger now.
        if total_d_count >= 1 or prob > 0.5:  # Lowered threshold slightly for testing
            row.update({
                'thoughts': thoughts, 
                'd_count': total_d_count, 
                'failure_prob': prob,
                'asset_id': current_asset_id # Use the cleaned ID
            })
            ANOMALY_QUEUE.append(row)
            
            # 5. Save history automatically
            call_db('save_history', method='POST', data={
                'asset_id': current_asset_id, 
                'failure_prob': prob, 
                'd_count': total_d_count, 
                'age_days': age_days, 
                'components': ", ".join(components_dict.keys())
            })

# --- ROUTES ---

@app.route('/scan', methods=['GET'])
def scan_assets():
    global LAST_ANOMALY_TIME, ANOMALY_QUEUE
    type_ = request.args.get('type', 'greeting')
    response = {"messages": [], "critical": False, "anomaly": None}
    current_time = int(time.time())
    
    if type_ == 'standby':
        if not ANOMALY_QUEUE: refresh_anomaly_queue()
        # Filter for existing assets (simplified via bridge check)
        if ANOMALY_QUEUE and current_time - LAST_ANOMALY_TIME >= COOLDOWN:
            anomaly = ANOMALY_QUEUE.pop(0)
            LAST_ANOMALY_TIME = current_time
            response['critical'] = True
            response['anomaly'] = {
                "id": anomaly['db_id'],
                "asset_id": anomaly['asset_id'],
                "damage_count": anomaly['d_count'],
                "failure_prob": anomaly['failure_prob'],
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
            "thoughts": a.get('thoughts', ""),
            "date_acquired": a.get('date_acquired')
        })
    return jsonify({"anomalies": result, "count": len(result)})

@app.route('/failure_by_month', methods=['GET'])
def failure_by_month():
    asset_id = request.args.get('asset_id')
    data = call_db('failure_history', asset_id)
    return jsonify({"monthly_failure_rates": data, "asset_id": asset_id})

@app.route('/repaired_by_month', methods=['GET'])
def repaired_by_month():
    asset_id = request.args.get('asset_id')
    data = call_db('repairs', asset_id)
    return jsonify({"monthly_repairs": data, "asset_id": asset_id})

@app.route('/maintenance_by_month', methods=['GET'])
def maintenance_by_month():
    asset_id = request.args.get('asset_id')
    data = call_db('maintenance', asset_id)
    return jsonify({"monthly_maintenance": data, "asset_id": asset_id})

@app.route('/last_maintenance', methods=['GET'])
def last_maintenance():
    asset_id = request.args.get('asset_id')
    res = call_db('last_maint', asset_id)
    return jsonify({"last_maintenance": res[0]['last_maintenance'] if res else None})

if __name__ == '__main__':
    # Use environment port for Render
    port = int(os.environ.get("PORT", 5000))
    app.run(host='0.0.0.0', port=port)

