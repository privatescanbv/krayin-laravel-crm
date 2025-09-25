from fastapi import FastAPI
from pydantic import BaseModel
import matplotlib.pyplot as plt
import pandas as pd
import base64
import re
from io import BytesIO
from langchain_openai import ChatOpenAI

app = FastAPI()

llm = ChatOpenAI(model="gpt-3.5-turbo", temperature=0)

class DataRequest(BaseModel):
    question: str
    query: str
    result: list  # data van query-agent (lijst met tuples)

def extract_aliases(sql: str):
    aliases = re.findall(r"AS\s+(\w+)", sql, flags=re.IGNORECASE)
    return aliases

@app.post("/visualize")
def visualize(data: DataRequest):
    try:
        print("DEBUG incoming result:", data.result)
        df = pd.DataFrame(data.result)
        print("DEBUG dataframe head:\n", df.head())

        if df.empty:
            return {"error": "Geen data ontvangen om te visualiseren."}

        # Probeer kolomnamen uit de SQL te halen
        aliases = extract_aliases(data.query)
        if aliases and len(aliases) == df.shape[1]:
            df.columns = aliases
        else:
            df.columns = [f"col_{i}" for i in range(df.shape[1])]

        # Convert kolommen naar numeriek waar mogelijk
        for col in df.columns:
            try:
                df[col] = pd.to_numeric(df[col])
            except Exception:
                pass

        # Plot
        fig, ax = plt.subplots()
        if "month" in df.columns and "leads_count" in df.columns:
            df.plot(x="month", y="leads_count", kind="bar", ax=ax)
        else:
            numeric_cols = df.select_dtypes(include=["number"]).columns
            if len(numeric_cols) >= 2:
                df.plot(x=numeric_cols[1], y=numeric_cols[0], kind="bar", ax=ax)
            else:
                return {"error": f"Geen geschikte kolommen gevonden. Kolommen: {df.dtypes.to_dict()}"}

        buf = BytesIO()
        plt.savefig(buf, format="png")
        buf.seek(0)
        img_base64 = base64.b64encode(buf.read()).decode("utf-8")
        plt.close(fig)

        return {
            "chart": img_base64,
            "analysis": f"Grafiek gegenereerd met kolommen {list(df.columns)}."
        }

    except Exception as e:
        try:
            plt.close('all')
        except Exception:
            pass
        return {"error": f"Visualisatie mislukt: {type(e).__name__}: {e}"}
