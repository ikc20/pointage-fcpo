import base64
import json
import os

import numpy as np
import cv2
from flask import Flask, request, jsonify
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

DATA_FILE = "known_faces.json"


def load_faces():
    if os.path.exists(DATA_FILE):
        with open(DATA_FILE, "r") as f:
            raw = json.load(f)
        # convert lists to numpy arrays
        return {k: np.array(v, dtype=np.float32) for k, v in raw.items()}
    return {}


def save_faces(known_faces):
    raw = {k: v.tolist() for k, v in known_faces.items()}
    with open(DATA_FILE, "w") as f:
        json.dump(raw, f)


known_faces = load_faces()


def b64_to_bgr(image_b64: str):
    # accepter "data:image/png;base64,...." ou juste base64
    if "," in image_b64:
        image_b64 = image_b64.split(",", 1)[1]
    img_bytes = base64.b64decode(image_b64)
    nparr = np.frombuffer(img_bytes, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)  # BGR
    if img is None:
        raise ValueError("Invalid image data")
    return img


def try_import_face_recognition():
    """Teste l'import pour le health check."""
    try:
        import face_recognition  # noqa: F401
        return True, None
    except Exception as e:
        return False, str(e)


@app.get("/health")
def health():
    ok, err = try_import_face_recognition()
    return jsonify({
        "success": True,
        "face_recognition_ready": ok,
        "error": err,
        "known_faces_count": len(known_faces),
    })


@app.post("/register")
def register():
    # 🔹 1) Vérifier que face_recognition est dispo
    try:
        import face_recognition
    except Exception as e:
        return jsonify({
            "success": False,
            "error": f"face_recognition import failed: {e}"
        }), 500

    try:
        data = request.get_json(force=True)
        employee_id = str(data["employee_id"])
        image_b64 = data["image"]

        # 🔹 2) Interdire un 2ᵉ enregistrement pour le même employé
        if employee_id in known_faces:
            return jsonify({
                "success": False,
                "error": f"Employee {employee_id} already registered"
            }), 400

        # 🔹 3) Encodage du visage
        img_bgr = b64_to_bgr(image_b64)
        img_rgb = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)

        encodings = face_recognition.face_encodings(img_rgb)
        if len(encodings) == 0:
            return jsonify({"success": False, "error": "No face detected"}), 400
        if len(encodings) > 1:
            return jsonify({"success": False, "error": "Multiple faces detected"}), 400

        known_faces[employee_id] = encodings[0].astype(np.float32)
        save_faces(known_faces)

        return jsonify({"success": True, "employee_id": employee_id})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


@app.post("/recognize")
def recognize():
    # 🔹 1) Vérifier que face_recognition est dispo
    try:
        import face_recognition
        from face_recognition import face_distance
    except Exception as e:
        return jsonify({
            "success": False,
            "error": f"face_recognition import failed: {e}"
        }), 500

    try:
        data = request.get_json(force=True)
        image_b64 = data["image"]

        img_bgr = b64_to_bgr(image_b64)
        img_rgb = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)

        encodings = face_recognition.face_encodings(img_rgb)
        if len(encodings) == 0:
            return jsonify({"success": False, "error": "No face detected"}), 400
        if len(encodings) > 1:
            return jsonify({"success": False, "error": "Multiple faces detected"}), 400

        if not known_faces:
            return jsonify({
                "success": True,
                "employee_id": None,
                "confidence": 0.0,
                "distance": None
            })

        encoding = encodings[0].astype(np.float32)

        ids = list(known_faces.keys())
        vectors = np.array([known_faces[i] for i in ids], dtype=np.float32)

        distances = face_distance(vectors, encoding)

        best_idx = int(np.argmin(distances))
        best_distance = float(distances[best_idx])

        # seuil classique ~0.6 (à ajuster)
        threshold = 0.60
        if best_distance > threshold:
            return jsonify({
                "success": True,
                "employee_id": None,
                "confidence": 0.0,
                "distance": best_distance
            })

        # convertir distance -> confidence (simple)
        confidence = max(0.0, min(1.0, 1.0 - (best_distance / threshold)))

        return jsonify({
            "success": True,
            "employee_id": ids[best_idx],
            "confidence": confidence,
            "distance": best_distance
        })
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


@app.post("/reset_faces")
def reset_faces():
    """
    ⚠️ À n'utiliser que côté admin / dev !
    Réinitialise complètement les visages connus.
    """
    global known_faces
    known_faces = {}
    save_faces(known_faces)
    return jsonify({"success": True, "message": "All faces cleared"})


if __name__ == "__main__":
    # important : même host/port que ce que Symfony appelle (127.0.0.1:5000)
    app.run(host="127.0.0.1", port=5000, debug=True)
