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


logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)



app = Flask(__name__)
CORS(app)

limiter = Limiter(
    get_remote_address,
    app=app,
    default_limits=["60 per minute"],
    storage_uri="memory://"
)

DEFAULT_THRESHOLD = float(os.environ.get("FACE_THRESHOLD", "0.50"))
MAX_IMAGE_SIZE = 5_000_000  # 5MB max



try:
    import face_recognition
    from face_recognition import face_distance
    FACE_LIB_READY = True
    FACE_LIB_ERROR = None
    logger.info(" face_recognition chargé avec succès")
except Exception as e:
    FACE_LIB_READY = False
    FACE_LIB_ERROR = str(e)
    logger.error(f" Erreur chargement face_recognition: {e}")



def b64_to_bgr(image_b64: str):
    """Convertit une image base64 en format BGR OpenCV"""
    if "," in image_b64:
        image_b64 = image_b64.split(",", 1)[1]

    if len(image_b64) > MAX_IMAGE_SIZE:
        raise ValueError(f"Image too large: {len(image_b64)} > {MAX_IMAGE_SIZE}")

    try:
        img_bytes = base64.b64decode(image_b64)
        nparr = np.frombuffer(img_bytes, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

        if img is None:
            raise ValueError("Invalid image data - decode returned None")

        return img
    except Exception as e:
        logger.error(f"Erreur décodage base64: {e}")
        raise

def encode_single_face(image_b64: str):
    """Extrait l'encodage facial d'une image"""
    if not FACE_LIB_READY:
        return None, f"face_recognition not available: {FACE_LIB_ERROR}"

    try:
        img_bgr = b64_to_bgr(image_b64)
        img_rgb = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)

        # Détection des visages
        face_locations = face_recognition.face_locations(img_rgb, model="hog")
        
        if len(face_locations) == 0:
            logger.warning("Aucun visage détecté")
            return None, "No face detected"

        if len(face_locations) > 1:
            logger.warning(f"Multiple faces detected: {len(face_locations)}")
            return None, "Multiple faces detected"

        # Encodage
        encodings = face_recognition.face_encodings(img_rgb, face_locations)
        
        if len(encodings) == 0:
            logger.warning("Encodage impossible")
            return None, "Unable to encode face"

        logger.info(f" Visage encodé avec succès")
        return encodings[0].astype(np.float32), None

    except Exception as e:
        logger.error(f"Erreur dans encode_single_face: {e}")
        return None, str(e)


# ROUTES

@app.get("/health")
def health():
    """Endpoint de santé du service"""
    return jsonify({
        "success": True,
        "face_recognition_ready": FACE_LIB_READY,
        "error": FACE_LIB_ERROR,
        "threshold": DEFAULT_THRESHOLD,
        "storage": "symfony_db"
    })

@app.post("/encode")
@limiter.limit("10 per minute")
def encode():
    """Encode un visage en vecteur 128 dimensions"""
    client_ip = request.remote_addr
    logger.info(f" Requête /encode depuis {client_ip}")
    
    try:
        data = request.get_json(force=True)
        if not isinstance(data, dict):
            logger.warning(f"JSON invalide depuis {client_ip}")
            return jsonify({"success": False, "error": "Invalid JSON"}), 400

        image_b64 = data.get("image")
        if not image_b64 or not isinstance(image_b64, str):
            logger.warning(f"Image manquante depuis {client_ip}")
            return jsonify({"success": False, "error": 'Missing "image" field'}), 400

        logger.info(f" Taille base64 reçue: {len(image_b64)} caractères")
        
        encoding, err = encode_single_face(image_b64)
        if err:
            logger.warning(f"Encodage échoué: {err}")
            return jsonify({"success": False, "error": err}), 400

        logger.info(f" Encodage réussi - 128 dimensions")
        
        return jsonify({
            "success": True,
            "encoding": encoding.tolist(),
            "threshold": DEFAULT_THRESHOLD
        })

    except Exception as e:
        logger.error(f" Erreur serveur /encode: {e}")
        logger.error(traceback.format_exc())
        return jsonify({"success": False, "error": str(e)}), 500

@app.post("/match")
@limiter.limit("20 per minute")
def match():
    """Compare un visage avec une liste de candidats"""
    client_ip = request.remote_addr
    logger.info(f" Requête /match depuis {client_ip}")
    
    try:
        if not FACE_LIB_READY:
            logger.error("Service IA non disponible")
            return jsonify({
                "success": False,
                "error": f"face_recognition not available: {FACE_LIB_ERROR}"
            }), 500

        data = request.get_json(force=True)
        if not isinstance(data, dict):
            logger.warning(f"JSON invalide depuis {client_ip}")
            return jsonify({"success": False, "error": "Invalid JSON"}), 400

        image_b64 = data.get("image")
        candidates = data.get("candidates", [])
        threshold = float(data.get("threshold", DEFAULT_THRESHOLD))

        logger.info(f" Seuil utilisé: {threshold}")
        logger.info(f"Nombre de candidats reçus: {len(candidates)}")

        if not image_b64 or not isinstance(image_b64, str):
            logger.warning(f"Image manquante depuis {client_ip}")
            return jsonify({"success": False, "error": 'Missing "image" field'}), 400

        # Encodage de l'image
        encoding, err = encode_single_face(image_b64)
        if err:
            logger.warning(f"Encodage échoué: {err}")
            return jsonify({"success": False, "error": err}), 400

        # Validation des candidats
        if not isinstance(candidates, list) or len(candidates) == 0:
            logger.info("Aucun candidat fourni")
            return jsonify({
                "success": True,
                "employee_id": None,
                "distance": None,
                "confidence": 0.0,
                "threshold": threshold
            })

        ids = []
        vectors = []
        invalid_candidates = 0

        for idx, c in enumerate(candidates):
            if not isinstance(c, dict):
                invalid_candidates += 1
                continue

            emp_id = c.get("employee_id")
            enc = c.get("encoding")

            if emp_id is None or enc is None:
                invalid_candidates += 1
                continue

            if not isinstance(enc, list) or len(enc) != 128:
                logger.warning(f"Candidat {idx}: encodage invalide (len={len(enc) if isinstance(enc, list) else 'not list'})")
                invalid_candidates += 1
                continue

            ids.append(str(emp_id))
            vectors.append(np.array(enc, dtype=np.float32))

        logger.info(f" Candidats valides: {len(vectors)}/{len(candidates)}")

        if len(vectors) == 0:
            logger.warning("Aucun candidat valide")
            return jsonify({
                "success": True,
                "employee_id": None,
                "distance": None,
                "confidence": 0.0,
                "threshold": threshold
            })

  
        vectors_np = np.vstack(vectors).astype(np.float32)
        distances = face_distance(vectors_np, encoding)
        
        best_idx = int(np.argmin(distances))
        best_distance = float(distances[best_idx])
        
        logger.info(f" Meilleure distance: {best_distance:.4f} (seuil: {threshold})")

        if best_distance > threshold:
            logger.info(f" Distance trop élevée: {best_distance:.4f} > {threshold}")
            return jsonify({
                "success": True,
                "employee_id": None,
                "distance": best_distance,
                "confidence": 0.0,
                "threshold": threshold
            })

        
        confidence = max(0.0, min(1.0, 1.0 - (best_distance / threshold)))
        
        logger.info(f" Match réussi - Employee ID: {ids[best_idx]}, Distance: {best_distance:.4f}, Confiance: {confidence:.2f}")

        return jsonify({
            "success": True,
            "employee_id": ids[best_idx],
            "distance": best_distance,
            "confidence": confidence,
            "threshold": threshold
        })

    except Exception as e:
        logger.error(f" Erreur serveur /match: {e}")
        logger.error(traceback.format_exc())
        return jsonify({"success": False, "error": str(e)}), 500



@app.errorhandler(429)
def ratelimit_handler(e):
    logger.warning(f"Rate limit dépassé - {request.remote_addr}")
    return jsonify({
        "success": False,
        "error": "Trop de requêtes. Veuillez réessayer plus tard."
    }), 429

@app.errorhandler(404)
def not_found(e):
    return jsonify({"success": False, "error": "Endpoint non trouvé"}), 404

@app.errorhandler(405)
def method_not_allowed(e):
    return jsonify({"success": False, "error": "Méthode non autorisée"}), 405



if __name__ == "__main__":
    logger.info("=" * 50)
    logger.info(" DÉMARRAGE DU SERVICE IA DE RECONNAISSANCE FACIALE")
    logger.info(f" Seuil par défaut: {DEFAULT_THRESHOLD}")
    logger.info(f" Taille max image: {MAX_IMAGE_SIZE/1024/1024:.1f}MB")
    logger.info(f" Rate limiter: 10/min encode, 20/min match")
    logger.info("=" * 50)
    
    app.run(host="0.0.0.0", port=5000, debug=True)