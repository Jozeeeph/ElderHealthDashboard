import os
import requests
import uvicorn
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

app = FastAPI()

MODEL_ID = os.getenv("HF_MODEL_ID", "meta-llama/Llama-3.1-8B-Instruct")
API_URL = os.getenv("HF_API_URL", "https://router.huggingface.co/v1/chat/completions")
HF_TOKEN = os.getenv("HF_TOKEN", "").strip()


class AnalyzeRequest(BaseModel):
    text: str


def _session_with_retry() -> requests.Session:
    session = requests.Session()
    retry_strategy = Retry(
        total=5,
        backoff_factor=2,
        status_forcelist=[429, 500, 502, 503, 504],
        allowed_methods=["POST"],
    )
    adapter = HTTPAdapter(max_retries=retry_strategy)
    session.mount("https://", adapter)
    return session


@app.post("/analyze")
async def analyze(req: AnalyzeRequest):
    if not HF_TOKEN:
        raise HTTPException(500, "HF_TOKEN manquant.")

    payload = {
        "model": MODEL_ID,
        "messages": [
            {
                "role": "system",
                "content": "Tu rediges des pre-bilans medicaux synthetiques sans poser de diagnostic definitif.",
            },
            {"role": "user", "content": req.text},
        ],
        "temperature": 0.2,
        "max_tokens": 700,
        "provider": os.getenv("HF_PROVIDER", "auto"),
    }
    headers = {"Authorization": f"Bearer {HF_TOKEN}", "Content-Type": "application/json"}

    session = _session_with_retry()
    try:
        resp = session.post(API_URL, headers=headers, json=payload, timeout=60)
    except requests.RequestException as e:
        raise HTTPException(502, f"Erreur reseau lors de l'appel HF: {e}")

    if resp.status_code != 200:
        raise HTTPException(resp.status_code, resp.text)

    try:
        data = resp.json()
    except ValueError:
        raise HTTPException(502, "Reponse non-JSON de HF API")

    content = data.get("choices", [{}])[0].get("message", {}).get("content")
    if not isinstance(content, str) or not content.strip():
        raise HTTPException(502, "Reponse IA vide ou format inattendu")

    return {"generated_text": content.strip()}


if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=5002)
