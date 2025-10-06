import fitz  # PyMuPDF
import chromadb
from langchain_community.embeddings import OpenAIEmbeddings
import os

# Vectorstore initialisatie
# client = chromadb.Client()
# collection = client.get_or_create_collection("bedrijf_docs")

EMBED_BASE = os.getenv("LLM_API_BASE_URL", "http://host.docker.internal:1234/v1")

# Gebruik altijd persistent storage
CHROMA_PATH = "/app/chroma"
client = chromadb.PersistentClient(path=CHROMA_PATH)

def embed_text(texts):
    # Gebruik lokale embedding model van LM Studio
    import requests
    vectors = []
    for t in texts:
        resp = requests.post(
            f"{EMBED_BASE}/embeddings",
            json={"model": "text-embedding-nomic-embed-text-v1.5", "input": t},
        )
        data = resp.json()
        vectors.append(data["data"][0]["embedding"])
    return vectors

def index_pdf(file_path):
    doc = fitz.open(file_path)
    collection_name = os.path.splitext(os.path.basename(file_path))[0]
    collection = client.get_or_create_collection(collection_name)
    chunks = []
    for page in doc:
        text = page.get_text("text")
        chunks.extend([text[i:i+1000] for i in range(0, len(text), 1000)])
    embeddings = embed_text(chunks)
    for i, emb in enumerate(embeddings):
        collection.add(documents=[chunks[i]], embeddings=[emb], ids=[f"chunk-{i}"])
    return len(chunks)

def query_docs(question, collection_name="voorwaardenmb", top_k=8, max_total_chars=8000):
    """Zoek relevante tekststukken in een specifieke Chroma-collectie."""
    import chromadb

    client = chromadb.PersistentClient(path="/app/chroma")

    # Controleer of collectie bestaat
    try:
        collection = client.get_collection(collection_name)
    except Exception:
        return f"Collectie '{collection_name}' niet gevonden."

    # Embed de vraag
    q_embed = embed_text([question])[0]

    # Zoek top matches
    results = collection.query(query_embeddings=[q_embed], n_results=top_k)

    if not results or "documents" not in results or not results["documents"][0]:
        return "Geen resultaten gevonden in de vectorstore."

    # Combineer context
    contexts = [r for r in results["documents"][0]]
    joined = "\n---\n".join(contexts)

    # Limiteer lengte
    if len(joined) > max_total_chars:
        joined = summarize_context(joined)

    return joined


def summarize_context(long_text):
    """Gebruik LM Studio om context korter te maken."""
    if len(long_text) < 4000:
        return long_text
    prompt = (
        "Vat onderstaande tekst kort samen in het Nederlands, behoud alleen belangrijke kerninformatie.\n\n"
        f"{long_text}"
    )
    from main import call_lm_studio
    summary = call_lm_studio(prompt)
    return summary[:4000]

def list_collections():
    """Geeft namen van alle aanwezige documenten (collecties) in Chroma."""
    try:
        import chromadb
#         client = chromadb.PersistentClient(path="/app/chroma")
        return [c.name for c in client.list_collections()]
    except Exception as e:
        return [f"Fout bij ophalen: {e}"]

def get_chroma_status():
    """Geeft statusinformatie over alle Chroma collecties."""
    import chromadb
#     client = chromadb.PersistentClient(path="/app/chroma")
    collections = []
    total = 0

    for c in client.list_collections():
        try:
            col = client.get_collection(c.name)
            count = len(col.get()["ids"])
        except Exception:
            count = 0
        collections.append({"name": c.name, "count": count})
        total += count

    return {
        "collections": collections,
        "total_collections": len(collections),
        "total_embeddings": total
    }
