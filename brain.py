from flask import Flask, request, jsonify
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
import joblib
import os
import json
import time
from flask_cors import CORS
from datetime import datetime
import logging

app = Flask(__name__)
CORS(app)

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

MODEL_FILE = 'asset_failure_model.pkl'

# ====================================
# GLOBAL STORAGE (In-Memory Cache)
# ====================================
ASSET_DATA_CACHE = {}
ANOMALY_QUEUE = []
LAST_ANOMALY_TIME = 0
COOLDOWN = 60  # seconds

# ====================================
# LOAD MODEL
# ====================================
if os.path.exists(MODEL_FILE):
    model = joblib.load(MODEL_FILE)
    logger.info("✓ Model loaded successfully")
else:
    model = None
    logger.warning("⚠ Model file not found. Anomaly detection disabled.")

# ====================================
# HELPER: ANALYZE ASSET
# ====================================
def analyze_asset(asset_id, d_count, age_days, components_dict=None, environment_score=5):
    """
    Analyze asset failure risk using ML model.
    Returns: (human_friendly_text, failure_probability)
    """
    if model is None:
        return "Model not available", 0.0

    if components_dict is None:
        components_dict = {}

    MODEL_FEATURES = [
        'd_count', 'age_days', 'environment_score', 'mishandling_score',
        'screen_failures', 'battery_failures', 'keyboard_failures', 'motherboard_failures'
    ]

    # Prepare input features
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

    # Calculate failure probability
    failure_prob = model.predict_proba(features)[0][1]

    # Human-friendly text
    if failure_prob > 0.8:
        cause = (
            f"🔴 HIGH RISK ({failure_prob:.1%}): Asset may fail soon. "
            f"It has {d_count} damaged components: {', '.join(components_dict.keys()) if components_dict else 'none'}. "
            f"Age: {age_days} days. Recommend urgent maintenance/replacement."
        )
    elif failure_prob > 0.5:
        cause = (
            f"🟡 MODERATE RISK ({failure_prob:.1%}): "
            f"Asset has {d_count} damaged components and is {age_days} days old. "
            f"Monitor and repair as needed."
        )
    else:
        cause = (
            f"🟢 LOW RISK ({failure_prob:.1%}): "
            f"Asset is stable with minimal/no damage. Good for continued use."
        )

    return cause, failure_prob

# ====================================
# ENDPOINT 1: RECEIVE ASSET DATA
# ====================================
@app.route('/api/receive_assets', methods=['POST'])
def receive_assets():
    """
    PHP sends asset data here.
    Expected JSON:
    {
        "assets": [
            {
                "asset_id": "LAP-001",
                "asset_name": "MacBook Pro",
                "date_acquired": "2023-01-15",
                "status": "Available",
                "damage_count": 2,
                "components": ["Screen", "Battery"]
            },
            ...
        ]
    }
    """
    try:
        data = request.get_json()
        
        if not data or 'assets' not in data:
            return jsonify({"error": "Invalid request format"}), 400

        assets = data['assets']
        
        # Cache the asset data
        global ASSET_DATA_CACHE
        ASSET_DATA_CACHE = {a['asset_id']: a for a in assets}

        logger.info(f"✓ Received {len(assets)} assets from PHP")

        return jsonify({
            "status": "success",
            "message": f"Received {len(assets)} assets",
            "count": len(assets)
        }), 200

    except Exception as e:
        logger.error(f"Error receiving assets: {e}")
        return jsonify({"error": str(e)}), 500

# ====================================
# ENDPOINT 2: ANALYZE & SCAN ASSETS
# ====================================
@app.route('/api/scan', methods=['GET'])
def scan_assets():
    """
    Scan assets for anomalies.
    Query params: type=standby (get one) or type=all (get all)
    """
    try:
        type_ = request.args.get('type', 'all')
        response = {
            "status": "success",
            "type": type_,
            "anomalies": [],
            "total_critical": 0,
            "timestamp": datetime.now().isoformat()
        }

        if not ASSET_DATA_CACHE:
            return jsonify({**response, "message": "No asset data received yet"}), 200

        # Analyze all assets
        anomalies = []
        for asset_id, asset in ASSET_DATA_CACHE.items():
            d_count = asset.get('damage_count', 0)
            age_days = 0

            # Calculate age
            date_acquired = asset.get('date_acquired')
            if date_acquired:
                try:
                    acq_date = datetime.strptime(date_acquired, "%Y-%m-%d")
                    age_days = (datetime.now() - acq_date).days
                except:
                    age_days = 0

            # Get components
            components = asset.get('components', [])
            if isinstance(components, str):
                components = [c.strip() for c in components.split(',')]

            components_dict = {c: 1 for c in components}

            # Analyze
            thoughts, failure_prob = analyze_asset(
                asset_id, d_count, age_days, components_dict
            )

            # Flag as anomaly if high risk or multiple damages
            if d_count >= 3 or failure_prob > 0.5:
                anomalies.append({
                    "asset_id": asset_id,
                    "asset_name": asset.get('asset_name', 'Unknown'),
                    "damage_count": d_count,
                    "failure_prob": round(failure_prob, 3),
                    "components": components,
                    "age_days": age_days,
                    "analysis": thoughts,
                    "status": asset.get('status', 'Unknown'),
                    "date_acquired": date_acquired
                })

        # Sort by failure probability (highest first)
        anomalies.sort(key=lambda x: x['failure_prob'], reverse=True)

        if type_ == 'standby' and anomalies:
            # Return only the most critical
            response['anomalies'] = [anomalies[0]]
            response['total_critical'] = 1
        else:
            # Return all anomalies
            response['anomalies'] = anomalies
            response['total_critical'] = len(anomalies)

        return jsonify(response), 200

    except Exception as e:
        logger.error(f"Error scanning assets: {e}")
        return jsonify({"error": str(e)}), 500

# ====================================
# ENDPOINT 3: GET ASSET DETAILS
# ====================================
@app.route('/api/asset/<asset_id>', methods=['GET'])
def get_asset_details(asset_id):
    """
    Get detailed analysis for a specific asset.
    """
    try:
        if asset_id not in ASSET_DATA_CACHE:
            return jsonify({"error": "Asset not found"}), 404

        asset = ASSET_DATA_CACHE[asset_id]
        d_count = asset.get('damage_count', 0)
        age_days = 0

        date_acquired = asset.get('date_acquired')
        if date_acquired:
            try:
                acq_date = datetime.strptime(date_acquired, "%Y-%m-%d")
                age_days = (datetime.now() - acq_date).days
            except:
                pass

        components = asset.get('components', [])
        if isinstance(components, str):
            components = [c.strip() for c in components.split(',')]

        components_dict = {c: 1 for c in components}
        thoughts, failure_prob = analyze_asset(asset_id, d_count, age_days, components_dict)

        return jsonify({
            "status": "success",
            "asset_id": asset_id,
            "asset_name": asset.get('asset_name'),
            "date_acquired": date_acquired,
            "age_days": age_days,
            "damage_count": d_count,
            "components": components,
            "failure_probability": round(failure_prob, 3),
            "analysis": thoughts,
            "status": asset.get('status'),
            "timestamp": datetime.now().isoformat()
        }), 200

    except Exception as e:
        logger.error(f"Error getting asset details: {e}")
        return jsonify({"error": str(e)}), 500

# ====================================
# ENDPOINT 4: HEALTH CHECK
# ====================================
@app.route('/api/health', methods=['GET'])
def health_check():
    """
    Simple health check endpoint.
    """
    return jsonify({
        "status": "healthy",
        "model_loaded": model is not None,
        "assets_cached": len(ASSET_DATA_CACHE),
        "timestamp": datetime.now().isoformat()
    }), 200

# ====================================
# ENDPOINT 5: CLEAR CACHE
# ====================================
@app.route('/api/clear_cache', methods=['POST'])
def clear_cache():
    """
    Clear the asset cache (admin only).
    """
    global ASSET_DATA_CACHE, ANOMALY_QUEUE
    ASSET_DATA_CACHE = {}
    ANOMALY_QUEUE = []
    
    return jsonify({
        "status": "success",
        "message": "Cache cleared"
    }), 200

# ====================================
# RUN APP
# ====================================
if __name__ == '__main__':
    logger.info("=" * 50)
    logger.info("🚀 Velyn Asset Analysis API")
    logger.info("=" * 50)
    logger.info("API Endpoints:")
    logger.info("  POST /api/receive_assets - Receive asset data from PHP")
    logger.info("  GET  /api/scan?type=all - Analyze assets for anomalies")
    logger.info("  GET  /api/scan?type=standby - Get most critical anomaly")
    logger.info("  GET  /api/asset/<asset_id> - Get asset analysis")
    logger.info("  GET  /api/health - Health check")
    logger.info("  POST /api/clear_cache - Clear cached data")
    logger.info("=" * 50)
    
    app.run(
        host='0.0.0.0',  # Listen on all interfaces
        port=5000,
        debug=True
    )
