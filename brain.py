from flask import Flask, request, jsonify
import pandas as pd
import joblib
import os
import time
import requests
from flask_cors import CORS
from datetime import datetime
from urllib.parse import unquote

app = Flask(__name__)
CORS(app)

# --- SETTINGS ---
MODEL_FILE = 'asset_failure_model.pkl'
PHP_URL = "https://velynasset.infinityfree.me/api.php" 

ANOMALY_QUEUE = []
LAST_ANOMALY_TIME = 0
COOLDOWN = 60 

if os.path.exists(MODEL_FILE):
    model = joblib.load(MODEL_FILE)
else:
    raise FileNotFoundError(f"Model file '{MODEL_FILE}' not found.")

def call_db(action, asset_id=None, method='GET', data=None):
    # Construct the query parameters
    params = {'action': action}
    if asset_id: 
        params['asset_id'] = asset_id 
    
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
        "Accept": "application/json"
    }

    try:
        # Prepare the request to show exactly what is being sent
        req = requests.Request(method, PHP_URL, params=params, json=data, headers=headers)
        prepared = req.prepare()
        
        print(f"--- DEBUG: Attempting API Call ---")
        print(f"URL: {prepared.url}")  # THIS IS THE LINK YOU CAN TEST IN BROWSER
        
        session = requests.Session()
        response = session.send(prepared, timeout=15)

        # 1. Check for HTTP Errors (404, 403, 500)
        if response.status_code != 200:
            print(f"Server returned error code: {response.status_code}")
            return []

        # 2. Check if the body is empty
        if not response.text.strip():
            print("Server returned an empty response.")
            return []

        # 3. Try parsing JSON
        return response.json()

    except requests.exceptions.JSONDecodeError:
        print("CRITICAL: Server sent HTML instead of JSON.")
        print(f"First 100 chars of response: {response.text[:100]}")
        return []
    except Exception as e:
        print(f"Bridge Error ({action}): {e}")
        return []

def analyze_asset(asset_id, d_count, age_days, components_dict=None):
    if components_dict is None: components_dict = {}
    MODEL_FEATURES = ['d_count', 'age_days', 'environment_score', 'mishandling_score',
                      'screen_failures', 'battery_failures', 'keyboard_failures', 'motherboard_failures']
    
    input_data = {'d_count': d_count, 'age_days': age_days, 'environment_score': 5, 'mishandling_score': 0}
    for c, count in components_dict.items():
        col_name = c.lower().replace(" ", "_") + "_failures"
        if col_name in MODEL_FEATURES:
            input_data[col_name] = count
        
    features = pd.DataFrame([input_data]).reindex(columns=MODEL_FEATURES, fill_value=0)
    failure_prob = model.predict_proba(features)[0][1]
    
    if failure_prob > 0.8: thoughts = f"High Risk ({failure_prob:.1%}). Urgent maintenance required."
    elif failure_prob > 0.5: thoughts = f"Moderate Risk ({failure_prob:.1%}). Monitor closely."
    else: thoughts = f"Low Risk ({failure_prob:.1%}). Stable."
    
    return thoughts, failure_prob

def refresh_anomaly_queue():
    global ANOMALY_QUEUE
    assets = call_db('get_assets')
    temp_queue = []
    
    if not assets or not isinstance(assets, list): return

    for row in assets:
        asset_id = row.get('asset_id', '')
        if not asset_id: continue

        # 1. Age
        dt_str = row.get('date_acquired')
        try:
            age_days = (datetime.now() - datetime.strptime(dt_str, '%Y-%m-%d')).days
        except:
            age_days = 0

        # 2. Damages
        comp_rows = call_db('get_damaged', asset_id)
        components_dict = {}
        total_d_count = 0
        
        if isinstance(comp_rows, list):
            for r in comp_rows:
                raw_comp = r.get('component', '')
                if raw_comp:
                    parts = [p.strip() for p in raw_comp.split(',') if p.strip()]
                    for p in parts:
                        key = p.lower().replace(" ", "_")
                        components_dict[key] = components_dict.get(key, 0) + 1
                        total_d_count += 1
        
        # 3. AI
        thoughts, prob = analyze_asset(asset_id, total_d_count, age_days, components_dict)
        
        if total_d_count >= 1 or prob > 0.5:
            row.update({'thoughts': thoughts, 'd_count': total_d_count, 'failure_prob': prob})
            temp_queue.append(row)
            
            # Save history
            call_db('save_history', method='POST', data={
                'asset_id': asset_id, 'failure_prob': prob, 
                'd_count': total_d_count, 'age_days': age_days, 
                'components': ", ".join(components_dict.keys())
            })
        time.sleep(0.1) # Small delay to prevent InfinityFree rate-limiting
    
    ANOMALY_QUEUE = temp_queue

@app.route('/all_anomalies', methods=['GET'])
def all_anomalies():
    refresh_anomaly_queue()
    return jsonify({"anomalies": ANOMALY_QUEUE, "count": len(ANOMALY_QUEUE)})

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    app.run(host='0.0.0.0', port=port)

