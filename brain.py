from flask import Flask, request, jsonify
import pandas as pd
import joblib
import os

app = Flask(__name__)
MODEL_FILE = 'asset_failure_model.pkl'

# Load model once at startup
if os.path.exists(MODEL_FILE):
    model = joblib.load(MODEL_FILE)
else:
    raise FileNotFoundError("Model file not found.")

def get_prediction(d_count, age_days, components_str):
    MODEL_FEATURES = [
        'd_count', 'age_days', 'environment_score', 'mishandling_score',
        'screen_failures', 'battery_failures', 'keyboard_failures', 'motherboard_failures'
    ]
    
    # Parse components string into counts
    input_data = {
        'd_count': d_count,
        'age_days': age_days,
        'environment_score': 5, # Default
        'mishandling_score': 0
    }
    
    for c in components_str.split(','):
        col = f"{c.strip().lower().replace(' ', '_')}_failures"
        if col in MODEL_FEATURES:
            input_data[col] = input_data.get(col, 0) + 1

    features = pd.DataFrame([input_data]).reindex(columns=MODEL_FEATURES, fill_value=0)
    prob = model.predict_proba(features)[0][1]
    return float(prob)

@app.route('/predict', methods=['POST'])
def predict():
    data = request.json
    assets = data.get('assets', [])
    results = []

    for asset in assets:
        prob = get_prediction(asset['d_count'], asset['age_days'], asset['components'])
        
        results.append({
            "asset_id": asset['asset_id'],
            "failure_prob": prob,
            "status": "High Risk" if prob > 0.8 else "Stable"
        })

    return jsonify({"predictions": results})

if __name__ == '__main__':
    app.run(port=5000)
