"""
Train a Naive Bayes classifier for instructor feedback.

Generates sample training data (60 labelled sentences approximating the
described 86.7% accuracy prototype), fits a TF-IDF + MultinomialNB pipeline,
and writes model.pkl + vectorizer.pkl next to this script.

Usage:
    pip install -r requirements.txt
    python train.py
"""

import os

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.naive_bayes import MultinomialNB
from sklearn.pipeline import Pipeline
import joblib

BASE = os.path.dirname(os.path.abspath(__file__))

FAST = [
    "Student is confident and drives smoothly on the road.",
    "Excellent control of the steering wheel and follows signs well.",
    "Very careful driver, already mastered parking and lane changing.",
    "Ready for the road test, no major errors observed.",
    "Learner is consistent and anticipates hazards properly.",
    "Good mirror usage and signaling at every turn.",
    "Mabilis matuto, mahusay ang paghawak sa manibela.",
    "Magaling mag-park at stable ang bilis ng takbo.",
    "Student responds well to instructions and stays calm.",
    "Smooth acceleration and braking throughout the session.",
    "Demonstrates proper defensive driving habits.",
    "Handles roundabouts and intersections without hesitation.",
    "Very minimal supervision needed during the drive.",
    "Understands right of way and yields correctly.",
    "Confident in both city and highway driving conditions.",
    "Good judgement when merging into traffic.",
    "Executes lane changes with proper blind spot checks.",
    "Maintains safe following distance consistently.",
    "Strong situational awareness behind the wheel.",
    "Completes maneuvers cleanly on first attempt.",
]

AVERAGE = [
    "Student is improving but still needs practice on parking.",
    "Decent driving, occasionally forgets to check mirrors.",
    "Average performance, manages speed but hesitates at turns.",
    "Needs standard review before the next session.",
    "Sometimes misses signals but generally follows rules.",
    "Moderate control, requires occasional reminders.",
    "Okay driving, still working on lane discipline.",
    "Manageable learner, makes small errors now and then.",
    "Comfortable on straight roads, unsure at intersections.",
    "Braking is a bit abrupt but acceptable.",
    "Needs more repetition on reversing and turning.",
    "Fair control of the wheel, watch speed on curves.",
    "Improves with each session but not yet consistent.",
    "Follows instructions but slow to react sometimes.",
    "Reasonable awareness, lapses in mirror checks.",
    "Steady pace, needs polishing on parallel parking.",
    "Can drive independently with light supervision.",
    "Understands basics, refine weak areas next time.",
    "Handles most situations, occasional guidance needed.",
    "Average learner, practice weak areas before next session.",
]

NEEDS = [
    "Student struggles with steering and loses control often.",
    "Very nervous, forgot to signal multiple times.",
    "Keeps making mistakes at intersections and stalls.",
    "Cannot maintain lane and drives too fast.",
    "Needs more intervention, barely follows instructions.",
    "Poor mirror usage and weak hazard perception.",
    "Mahirap kontrolin ang sasakyan, maraming pagkakamali.",
    "Kulang pa ang kumpiyansa sa pag-drive.",
    "Repeatedly forgot braking distance and nearly hit a post.",
    "Cannot parallel park even after several attempts.",
    "Confuses pedals and requires constant correction.",
    "Weak at judging gaps in traffic.",
    "Fails to yield and needs verbal guidance constantly.",
    "Unstable speed control, jerky movements.",
    "Does not check blind spots before changing lanes.",
    "Struggles with clutch control and stalls frequently.",
    "Poor spatial awareness when reversing.",
    "Needs close supervision for every maneuver.",
    "Forgets seatbelt and mirror adjustment routines.",
    "Lacks confidence and makes basic errors often.",
]

TEXTS = FAST + AVERAGE + NEEDS
LABELS = (["fast"] * len(FAST)) + (["average"] * len(AVERAGE)) + (["needs"] * len(NEEDS))


def main():
    pipeline = Pipeline([
        ("tfidf", TfidfVectorizer(ngram_range=(1, 2), min_df=1)),
        ("nb", MultinomialNB()),
    ])
    pipeline.fit(TEXTS, LABELS)

    joblib.dump(pipeline.named_steps["nb"], os.path.join(BASE, "model.pkl"))
    joblib.dump(pipeline.named_steps["tfidf"], os.path.join(BASE, "vectorizer.pkl"))

    print(f"Trained on {len(TEXTS)} samples. Saved model.pkl and vectorizer.pkl.")


if __name__ == "__main__":
    main()
