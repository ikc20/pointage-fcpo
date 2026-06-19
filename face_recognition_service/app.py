import base64
import os
import numpy as np
import cv2
import logging
import traceback
from flask import Flask, request, jsonify
from flask_cors import CORS
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address

# =========================================================
# LOGGING
# =========================================================
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)
logger = logging.getLogger("face-service")

# =========================================================
# APP
# =========================================================
app = Flask(__name__)

# 🔐 CORS restreint (mobile + backend)
CORS(
    app,
    resources={
        r"/encode": {"origins": "*"},
        r"/match": {"origins": "*"},
        r"/health": {"origins": "*"}
    }
)

# =========================================================
# RATE LIMITER
# =========================================================
limiter = Limiter(
    get_remote_address,
    app=app,
    default_limits=["60 per minute"],
    storage_uri="memory://"  # ⚠️ DEV ONLY
)

# =========================================================
# CONFIG
# =========================================================
DEFAULT_THRESHOLD = float(os.environ.get("FACE_THRESHOLD", "0.50"))
MAX_IMAGE_BYTES = 5_000_000  # 5 MB réel après décodage

# =========================================================
# FACE LIB
# =========================================================
try:
    import face_recognition
    from face_recognition import face_distance
    FACE_LIB_READY = True
    FACE_LIB_ERROR = None
    logger.info("✅ face_recognition chargé")
except Exception as e:
    FACE_LIB_READY = False
    FACE_LIB_ERROR = str(e)
    logger.error(f"❌ face_recognition indisponible: {e}")

# =========================================================
# UTILS
# =========================================================
def b64_to_bgr(image_b64: str):
    if "," in image_b64:
        image_b64 = image_b64.split(",", 1)[1]

    try:
        img_bytes = base64.b64decode(image_b64, validate=True)

        if len(img_bytes) > MAX_IMAGE_BYTES:
            raise ValueError("Image trop volumineuse")

        nparr = np.frombuffer(img_bytes, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

        if img is None:
            raise ValueError("Décodage OpenCV impossible")

        return img

    except Exception as e:
        logger.warning(f"Erreur décodage image: {e}")
        raise


def encode_single_face(image_b64: str):
    if not FACE_LIB_READY:
        return None, FACE_LIB_ERROR

    try:
        img_bgr = b64_to_bgr(image_b64)
        img_rgb = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)

        locations = face_recognition.face_locations(img_rgb, model="hog")

        if len(locations) == 0:
            return None, "Aucun visage détecté"

        if len(locations) > 1:
            return None, "Plusieurs visages détectés"

        encodings = face_recognition.face_encodings(img_rgb, locations)

        if not encodings:
            return None, "Encodage impossible"

        return encodings[0].astype(np.float32), None

    except Exception as e:
        logger.error(traceback.format_exc())
        return None, str(e)

# =========================================================
# ROUTES
# =========================================================
@app.get("/health")
def health():
    return jsonify({
        "success": FACE_LIB_READY,
        "face_recognition_ready": FACE_LIB_READY,
        "error": FACE_LIB_ERROR,
        "threshold": DEFAULT_THRESHOLD,
        "storage": "symfony_db"
    }), (200 if FACE_LIB_READY else 503)


@app.post("/encode")
@limiter.limit("10 per minute")
def encode():
    try:
        data = request.get_json(force=True)
        image_b64 = data.get("image") if isinstance(data, dict) else None

        if not image_b64:
            return jsonify({"success": False, "error": "Image manquante"}), 400

        encoding, err = encode_single_face(image_b64)
        if err:
            return jsonify({"success": False, "error": err}), 400

        return jsonify({
            "success": True,
            "encoding": encoding.tolist(),
            "threshold": DEFAULT_THRESHOLD
        })

    except Exception as e:
        logger.error(traceback.format_exc())
        return jsonify({"success": False, "error": "Erreur serveur"}), 500


@app.post("/match")
@limiter.limit("20 per minute")
def match():
    try:
        data = request.get_json(force=True)
        image_b64 = data.get("image")
        candidates = data.get("candidates", [])
        threshold = float(data.get("threshold", DEFAULT_THRESHOLD))


        app.logger.info(f"📥 Requête /match reçue")
        app.logger.info(f"   - Taille image: {len(image_b64) if image_b64 else 0}")
        app.logger.info(f"   - Nombre candidats: {len(candidates)}")
        app.logger.info(f"   - Threshold: {threshold}")

        encoding, err = encode_single_face(image_b64)
        if err:
            return jsonify({"success": False, "error": err}), 400

        valid_ids = []
        vectors = []

        for c in candidates:
            if isinstance(c, dict) and isinstance(c.get("encoding"), list) and len(c["encoding"]) == 128:
                valid_ids.append(str(c["employee_id"]))
                vectors.append(np.array(c["encoding"], dtype=np.float32))

        if not vectors:
            return jsonify({
                "success": True,
                "employee_id": None,
                "distance": None,
                "confidence": 0.0,
                "threshold": threshold
            })

        distances = face_distance(np.vstack(vectors), encoding)
        best_idx = int(np.argmin(distances))
        best_distance = float(distances[best_idx])

        if best_distance > threshold:
            return jsonify({
                "success": True,
                "employee_id": None,
                "distance": best_distance,
                "confidence": 0.0,
                "threshold": threshold
            })

        confidence = round(1 - (best_distance / threshold), 3)

        return jsonify({
            "success": True,
            "employee_id": valid_ids[best_idx],
            "distance": best_distance,
            "confidence": confidence,
            "threshold": threshold
        })

    except Exception:
        logger.error(traceback.format_exc())
        return jsonify({"success": False, "error": "Erreur serveur"}), 500


@app.errorhandler(429)
def rate_limit(e):
    return jsonify({"success": False, "error": "Trop de requêtes"}), 429


if __name__ == "__main__":
    logger.info("🚀 Face Recognition Service démarré")
    app.run(host="0.0.0.0", port=5000, debug=False)
