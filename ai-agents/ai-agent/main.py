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

# Logging
logging.basicConfig(level=logging.INFO)

app = FastAPI()

# DB connectie
db_user = os.getenv("DB_USER", "root")
db_pass = os.getenv("DB_PASS", "password")
db_host = os.getenv("DB_HOST", "mysql")
db_name = os.getenv("DB_NAME", "crm_data")

db_url = f"mysql+pymysql://{db_user}:{db_pass}@{db_host}:3306/{db_name}"

db = SQLDatabase.from_uri(
    db_url,
    include_tables=["leads", "lead_types", "users", "persons"],
    sample_rows_in_table_info=0  # geen voorbeelddata meesturen naar OpenAI
)

# LLM
llm = ChatOpenAI(model="gpt-3.5-turbo", temperature=0)

# SQL query generator
generate_query = create_sql_query_chain(llm, db)

class Query(BaseModel):
    question: str

@app.post("/chat")
def chat(query: Query):
    try:
        # SQL genereren
        sql = generate_query.invoke({
            "question": query.question + " Voeg GEEN puntkomma toe aan het einde van de query"
        })
        logging.info(f"Generated SQL: {sql}")

        try:
            # SQL uitvoeren
            result = db.run(sql)
            logging.info(f"Result: {result}")

            # Geen resultaat
            if not result or result == [] or result == "[]":
                return {
                    "answer": "Er zijn geen resultaten gevonden voor je vraag.",
                    "query": sql
                }

            # Normaal tekstantwoord
            if not any(word in query.question.lower() for word in ["grafiek", "diagram", "chart", "visualisatie"]):
                if isinstance(result, list) and len(result) > 0 and isinstance(result[0], tuple):
                    value = result[0][0]
                else:
                    value = result
                return {"answer": str(value), "query": sql}

            # Als de vraag om een grafiek gaat → stuur door naar ai-visualizer
            logging.info("Forwarding result to visualizer agent...")
            try:
                # result is bv. [(1,1), (1,2), (7,3)]
                raw_result = db.run(sql)
                try:
                    result = ast.literal_eval(raw_result)  # van string → echte lijst van tuples
                except Exception:
                    result = []

                safe_result = [list(r) for r in result]  # nu werkt dit wél
                print("DEBUG sending result to visualizer:", safe_result)

                response = requests.post(
                    "http://ai-visualizer:8002/visualize",
                    json={
                        "question": query.question,
                        "query": sql,
                        "result": safe_result
                    },
                    timeout=60
                )
                return response.json()
            except Exception as e:
                logging.error(f"Visualizer call failed: {e}")
                return {
                    "answer": "De data is opgehaald, maar de visualizer kon geen grafiek genereren.",
                    "query": sql,
                    "error": str(e)
                }

        except Exception as e:
            logging.error(f"SQL execution error: {e}")
            return {
                "answer": f"De query kon niet uitgevoerd worden: {e}",
                "query": sql
            }

    except Exception as e:
        logging.error(f"Query generation: {e}")
        return {
            "answer": f"De query kon niet uitgevoerd worden: {e}",
            "query": sql
        }
