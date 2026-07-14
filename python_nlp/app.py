"""
RC Driving Academy — IMAS
Flask NLP service for instructor feedback classification.

Endpoint:
  POST /classify
  Body:  {"text": "instructor feedback here"}
  Returns:
  {
    "classification": "fast" | "average" | "needs",
    "label": "Fast Learner" | "Average Learner" | "Needs Improvement",
    "confidence": 91.4,
    "recommendation": "...",
    "override_applied": false | true
  }

The service loads a trained Naive Bayes model (model.pkl + vectorizer.pkl) if
present. If the model is missing it falls back to a keyword heuristic so the
system is usable out of the box. The SAFETY OVERRIDE always wins: if any danger
keyword is present the result is forced to "needs" regardless of model score.
"""

import os
import re

from flask import Flask, jsonify, request

app = Flask(__name__)

try:
    from flask_cors import CORS
    CORS(app)
except ImportError:  # flask-cors is optional
    pass

BASE = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE, "model.pkl")
VECTORIZER_PATH = os.path.join(BASE, "vectorizer.pkl")

# Danger keywords — presence forces classification to "needs" (safety override).
SAFETY_KEYWORDS = [
    "bumangga", "collision", "near-collision", "dangerous", "hazard", "crash",
    "accident", "took control", "had to take control", "emergency",
    "unsafe speed", "erratic", "delikado", "mapanganib", "muntik na kaming bumangga",
]

LABELS = {
    "fast": "Fast Learner",
    "average": "Average Learner",
    "needs": "Needs Improvement",
}

RECOMMENDATIONS = {
    "fast": "Ready for certification. No additional sessions required.",
    "average": "Standard review recommended. Practice weak areas before next session.",
    "needs": "Mandatory intervention. Extended hours required before reassessment.",
}

_model = None
_vectorizer = None
_model_loaded = False


def _load_model():
    global _model, _vectorizer, _model_loaded
    if _model_loaded:
        return
    _model_loaded = True
    try:
        import joblib

        if os.path.exists(MODEL_PATH) and os.path.exists(VECTORIZER_PATH):
            _vectorizer = joblib.load(VECTORIZER_PATH)
            _model = joblib.load(MODEL_PATH)
            print("[NLP] Loaded trained model.")
    except Exception as exc:  # pragma: no cover
        print(f"[NLP] Model not loaded ({exc}); using heuristic fallback.")


def has_safety_keyword(text: str) -> bool:
    lowered = text.lower()
    return any(kw in lowered for kw in SAFETY_KEYWORDS)


def heuristic_classify(text: str):
    """Keyword-based fallback classifier (no trained model required)."""
    t = text.lower()
    positive = ["good", "confident", "smooth", "mastered", "excellent", "careful",
                "consistent", "ready", "mabilis", "mahusay", "magaling"]
    negative = ["struggle", "slow", "nervous", "forgot", "error", "mistake",
                "hindi", "mahirap", "kulang", "weak"]

    pos = sum(1 for w in positive if w in t)
    neg = sum(1 for w in negative if w in t)

    if pos > neg:
        return "fast", 80.0 + min(pos * 2, 15)
    if neg >= pos and neg > 0:
        return "needs", 75.0 + min(neg * 2, 20)
    return "average", 70.0


def classify_text(text: str):
    _load_model()
    override = has_safety_keyword(text)

    if _model is not None and _vectorizer is not None:
        vec = _vectorizer.transform([text])
        pred = _model.predict(vec)[0]
        proba = _model.predict_proba(vec)[0]
        confidence = round(float(max(proba)) * 100, 1)
        classification = pred
    else:
        classification, confidence = heuristic_classify(text)

    if override:
        classification = "needs"
        confidence = max(confidence, 99.0)

    return {
        "classification": classification,
        "label": LABELS[classification],
        "confidence": confidence,
        "recommendation": RECOMMENDATIONS[classification],
        "override_applied": override,
    }


@app.route("/classify", methods=["POST"])
def classify():
    data = request.get_json(silent=True) or {}
    text = (data.get("text") or "").strip()
    if not text:
        return jsonify({"error": "Field 'text' is required."}), 400
    return jsonify(classify_text(text))


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
