import base64
import os
import numpy as np
import cv2
from flask import Flask, request, jsonify
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

DEFAULT_THRESHOLD = float(os.environ.get("FACE_THRESHOLD", "0.60"))


def try_import_face_recognition():
    try:
        import face_recognition  # noqa: F401
        return True, None
    except Exception as e:
        return False, str(e)


def b64_to_bgr(image_b64: str):
    # accepte "data:image/png;base64,...." ou juste base64
    if "," in image_b64:
        image_b64 = image_b64.split(",", 1)[1]
    img_bytes = base64.b64decode(image_b64)
    nparr = np.frombuffer(img_bytes, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)  # BGR
    if img is None:
        raise ValueError("Invalid image data")
    return img


def encode_single_face(image_b64: str):
    """
    Retourne (encoding: np.ndarray shape(128,), error: str|None)
    """
    ok, err = try_import_face_recognition()
    if not ok:
        return None, f"face_recognition import failed: {err}"

    import face_recognition

    img_bgr = b64_to_bgr(image_b64)
    img_rgb = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)

    encodings = face_recognition.face_encodings(img_rgb)
    if len(encodings) == 0:
        return None, "No face detected"
    if len(encodings) > 1:
        return None, "Multiple faces detected"

    return encodings[0].astype(np.float32), None


@app.get("/health")
def health():
    ok, err = try_import_face_recognition()
    return jsonify({
        "success": True,
        "face_recognition_ready": ok,
        "error": err,
        "threshold": DEFAULT_THRESHOLD,
        "storage": "symfony_db",
    })


@app.post("/encode")
def encode():
    """
    Input JSON: { "image": "<base64>" }
    Output JSON: { success, encoding: [..128..], threshold }
    """
    try:
        data = request.get_json(force=True)
        image_b64 = data.get("image")
        if not image_b64 or not isinstance(image_b64, str):
            return jsonify({"success": False, "error": 'Missing "image" field'}), 400

        encoding, err = encode_single_face(image_b64)
        if err:
            return jsonify({"success": False, "error": err}), 400

        return jsonify({
            "success": True,
            "encoding": encoding.tolist(),
            "threshold": DEFAULT_THRESHOLD,
        })
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


@app.post("/match")
def match():
    """
    Input JSON:
    {
      "image": "<base64>",
      "candidates": [
        { "employee_id": "1", "encoding": [..128..] },
        { "employee_id": "2", "encoding": [..128..] }
      ],
      "threshold": 0.60 (optionnel)
    }

    Output JSON:
    {
      "success": true,
      "employee_id": "1" | null,
      "distance": 0.43 | null,
      "confidence": 0..1,
      "threshold": 0.60
    }
    """
    try:
        ok, err = try_import_face_recognition()
        if not ok:
            return jsonify({"success": False, "error": f"face_recognition import failed: {err}"}), 500

        import face_recognition
        from face_recognition import face_distance

        data = request.get_json(force=True)
        image_b64 = data.get("image")
        candidates = data.get("candidates", [])
        threshold = float(data.get("threshold", DEFAULT_THRESHOLD))

        if not image_b64 or not isinstance(image_b64, str):
            return jsonify({"success": False, "error": 'Missing "image" field'}), 400

        # 1) encoder l'image input
        encoding, err = encode_single_face(image_b64)
        if err:
            return jsonify({"success": False, "error": err}), 400

        # 2) vérifier candidats
        if not isinstance(candidates, list) or len(candidates) == 0:
            return jsonify({
                "success": True,
                "employee_id": None,
                "distance": None,
                "confidence": 0.0,
                "threshold": threshold,
                "reason": "No candidates provided"
            })

        ids = []
        vectors = []

        for c in candidates:
            if not isinstance(c, dict):
                continue
            emp_id = c.get("employee_id")
            enc = c.get("encoding")
            if emp_id is None or enc is None:
                continue
            if not isinstance(enc, list) or len(enc) != 128:
                continue

            ids.append(str(emp_id))
            vectors.append(np.array(enc, dtype=np.float32))

        if len(vectors) == 0:
            return jsonify({
                "success": True,
                "employee_id": None,
                "distance": None,
                "confidence": 0.0,
                "threshold": threshold,
                "reason": "Candidates invalid/empty"
            })

        vectors_np = np.vstack(vectors).astype(np.float32)

        # 3) distances
        distances = face_distance(vectors_np, encoding)
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

        confidence = max(0.0, min(1.0, 1.0 - (best_distance / threshold)))

        return jsonify({
            "success": True,
            "employee_id": ids[best_idx],
            "distance": best_distance,
            "confidence": confidence,
            "threshold": threshold
        })

    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=True)
