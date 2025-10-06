from fastapi import FastAPI
from pydantic import BaseModel
from langchain_openai import ChatOpenAI
from langchain_community.utilities.sql_database import SQLDatabase
from langchain.chains import create_sql_query_chain
import os
import logging
import requests
import json
import ast
import re
from fastapi import FastAPI, File, UploadFile
from rag import list_collections, get_chroma_status

# --- Logging ---
logging.basicConfig(level=logging.INFO)
app = FastAPI()

# --- DB connectie ---
db_user = os.getenv("DB_USER", "root")
db_pass = os.getenv("DB_PASS", "password")
db_host = os.getenv("DB_HOST", "mysql")
db_name = os.getenv("DB_NAME", "crm_data")

db_url = f"mysql+pymysql://{db_user}:{db_pass}@{db_host}:3306/{db_name}"

db = SQLDatabase.from_uri(
    db_url,
    include_tables=[
        "lead_channels", "lead_persons", "lead_pipeline_stages",
        "lead_pipelines", "lead_products", "lead_quotes", "lead_sources",
        "lead_stages", "lead_tags", "lead_types", "leads", "emails",
        "lead_types", "users", "persons"
    ],
    sample_rows_in_table_info=0,
)

# --- LM Studio omgeving ---
LM_API_BASE = os.getenv("LLM_API_BASE_URL", "http://host.docker.internal:1234/v1")
LM_MODEL = os.getenv("LM_MODEL", "deepseek-r1-distill-qwen-7b")
LM_API_KEY = os.getenv("LLM_API_KEY", "dummy")

# --- Standaard LLM (voor SQL-generatie) ---
llm = ChatOpenAI(model="gpt-3.5-turbo", temperature=0)
generate_query = create_sql_query_chain(llm, db)

class Query(BaseModel):
    question: str

def call_lm_studio(prompt: str, system_prompt: str = "Je bent een behulpzame AI assistent.", mode="default"):
    """Stuurt een bericht naar het lokale LM Studio model"""
    try:
        # Model selecteren
        if mode == "text":
            model = os.getenv("LLM_MODEL_TEXT")
        elif mode == "default":
            model = os.getenv("LLM_MODEL_DEFAULT", os.getenv("LLM_MODEL"))
        else:
            model = os.getenv("LLM_MODEL")

        # Endpoint bepalen (chat of completions)
        if "mistral" in model.lower():
            endpoint = f"{LM_API_BASE}/completions"
            payload = {
                "model": model,
                "prompt": f"{system_prompt}\n\n{prompt}",
                "max_tokens": 500
            }
        else:
            endpoint = f"{LM_API_BASE}/chat/completions"
            payload = {
                "model": model,
                "messages": [
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": prompt}
                ],
                "max_tokens": 500
            }

        response = requests.post(
            endpoint,
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {LM_API_KEY}",
            },
            json=payload,
            timeout=90,
        )
        print("DEBUG raw response:", response.text)  # tijdelijk debuggen
        data = response.json()

        if "choices" in data and len(data["choices"]) > 0:
            if "message" in data["choices"][0]:
                answer = data["choices"][0]["message"]["content"]
            else:
                answer = data["choices"][0].get("text", "")
        else:
            answer = "(leeg antwoord van model)"

        answer = re.sub(r"<think>.*?</think>", "", answer, flags=re.DOTALL).strip()
        return answer

    except Exception as e:
        logging.error(f"LM Studio call failed: {e}")
        return f"Fout bij verbinden met LM Studio: {e}"



@app.post("/chat")
def chat(query: Query):
    q = query.question.strip()

    # 1️⃣ Vrije taalvraag via LM Studio
    if q.lower().startswith("lm:"):
        logging.info("Routing to LM Studio (freeform)...")
        prompt = q[3:].strip()
        answer = call_lm_studio(prompt)
        return {"answer": answer, "model": LM_MODEL}

    # 2️⃣ Analyse-vraag: data ophalen + LLM samenvatting
    if q.lower().startswith("analyse:"):
        logging.info("Running hybrid analysis via LM Studio...")
        user_question = q[9:].strip()
        try:
            sql = generate_query.invoke({
                "question": user_question + " Voeg GEEN puntkomma toe aan het einde van de query"
            })
            result = db.run(sql)

            data_preview = json.dumps(result[:20], ensure_ascii=False) if isinstance(result, list) else str(result)

            prompt = (
                f"Hieronder staat CRM-data uit de database in JSON-formaat.\n"
                f"Beantwoord de vraag van de gebruiker met een begrijpelijke samenvatting, "
                f"analyseer trends of opvallende patronen.\n\n"
                f"Data:\n{data_preview}\n\n"
                f"Vraag van gebruiker: {user_question}"
            )

            answer = call_lm_studio(
                prompt,
                system_prompt="Je bent een data-analist die CRM-data uitlegt in gewone taal."
            )
            return {"answer": answer, "query": sql, "model": LM_MODEL}

        except Exception as e:
            logging.error(f"Analyse-mode error: {e}")
            return {"answer": f"Fout tijdens analyse: {e}"}

    # 3️⃣ Standaard SQL-agent flow (zoals eerder)
    try:
        sql = generate_query.invoke({
            "question": q + " Voeg GEEN puntkomma toe aan het einde van de query"
        })
        logging.info(f"Generated SQL: {sql}")

        try:
            result = db.run(sql)
            logging.info(f"Result: {result}")

            if not result or result == [] or result == "[]":
                return {"answer": "Er zijn geen resultaten gevonden voor je vraag.", "query": sql}

            if not any(word in q.lower() for word in ["grafiek", "diagram", "chart", "visualisatie"]):
                value = result[0][0] if isinstance(result, list) and len(result) > 0 and isinstance(result[0], tuple) else result
                return {"answer": str(value), "query": sql}

            logging.info("Forwarding result to visualizer agent...")
            raw_result = db.run(sql)
            try:
                result = ast.literal_eval(raw_result)
            except Exception:
                result = []
            safe_result = [list(r) for r in result]
            response = requests.post(
                "http://ai-visualizer:8002/visualize",
                json={"question": q, "query": sql, "result": safe_result},
                timeout=60,
            )
            return response.json()

        except Exception as e:
            logging.error(f"SQL execution error: {e}")
            return {"answer": f"De query kon niet uitgevoerd worden: {e}", "query": sql}

    except Exception as e:
        logging.error(f"Query generation: {e}")
        return {"answer": f"De query kon niet uitgevoerd worden: {e}"}


@app.post("/write")
def write_text(query: Query):
    prompt = query.question.strip()
    # Zorg dat dit altijd dominant is
    system_prompt = (
        "Je bent een Nederlandstalige tekstschrijver en beantwoordt "
        "alle vragen volledig in het Nederlands. "
        "Gebruik nooit Engels tenzij expliciet gevraagd. "
        "Schrijf kort, duidelijk en natuurlijk, passend bij MKB-communicatie."
    )
    answer = call_lm_studio(prompt, mode="text", system_prompt=system_prompt)
    return {"answer": answer}

from rag import index_pdf, query_docs

@app.post("/upload_pdf")
def upload_pdf(file: UploadFile = File(...)):
    path = f"/tmp/{file.filename}"
    with open(path, "wb") as f:
        f.write(file.file.read())
    n = index_pdf(path)
    return {"message": f"{file.filename} geïndexeerd ({n} tekstblokken)"}

@app.post("/ask_pdf")
def ask_pdf(query: Query):
    context = query_docs(query.question)
    prompt = (
        f"Gebruik de volgende context uit het bedrijfsdocument om de vraag te beantwoorden.\n\n"
        f"{context}\n\n"
        f"Vraag: {query.question}\n\n"
        f"Beantwoord in het Nederlands, helder en feitelijk."
    )
    answer = call_lm_studio(prompt)
    return {"answer": answer}

@app.delete("/delete_doc")
def delete_doc(name: str):
    """Verwijder een document/collectie uit de vectorstore"""
    import chromadb
    client = chromadb.PersistentClient(path="/app/chroma")

    existing = [c.name for c in client.list_collections()]
    if name not in existing:
        return {"status": "not_found", "message": f"Geen collectie '{name}' gevonden."}

    client.delete_collection(name)
    return {"status": "deleted", "message": f"Document '{name}' verwijderd."}

@app.get("/list_docs")
def list_docs():
    """Geef een lijst van alle beschikbare documenten (vectorcollecties)."""
    collections = list_collections()
    return {"collections": collections}

@app.get("/chroma_status")
def chroma_status():
    """Toon statistieken over de vectorstore."""
    status = get_chroma_status()
    return status
