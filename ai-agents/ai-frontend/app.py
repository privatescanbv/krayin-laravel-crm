import io

import pypdf
import requests
import streamlit as st
import base64
import time

st.set_page_config(page_title="CRM AI Agent", page_icon="🤖", layout="wide")

st.title("🤖 CRM AI Agent")

st.markdown("""
Welkom bij de **CRM AI Agent**.
Je kunt kiezen tussen:
- 💬 *CRM-vraag* → haalt data op uit je CRM en beantwoordt via de AI-agent
- ✍️ *Tekst schrijven* → gebruikt het schrijvende model (Mistral / Phi-3) in LM Studio
- 📄 *Bedrijfsdocument* → stel vragen over een geüpload PDF-bestand (zoals voorwaarden, handleidingen, etc.)
- 🏥 *Medische analyse* → genereer een medische samenvatting voor een arts op basis van een patiëntformulier
""")

# --- Keuze tussen CRM, tekst, document of medisch ---
option = st.radio(
    "Kies functie:",
    ["CRM-vraag", "Tekst schrijven", "Bedrijfsdocument", "Medische analyse"],
    horizontal=True,
)

# --- Session state voor bewerkbare medische prompt ---
if "med_prompt" not in st.session_state:
    try:
        r = requests.get("http://ai-agent:8001/med/default_prompt", timeout=5)
        st.session_state.med_prompt = r.json().get("prompt", "")
    except Exception:
        st.session_state.med_prompt = "Analyseer de volgende medische tekst en maak een samenvatting voor een arts.\n\nTekst:\n{text}"

# --- Optioneel schrijfstijl-menu voor de tekstmodus ---
tone = None
if option == "Tekst schrijven":
    tone = st.selectbox(
        "Kies schrijfstijl:",
        ["Zakelijk", "Informeel", "Marketing", "Korte pitch"],
        index=0,
    )
    st.caption("💡 Tip: De stijl beïnvloedt toon en woordkeuze van de AI-tekst.")

# Ophalen lijst van geïndexeerde documenten
docs = requests.get("http://ai-agent:8001/list_docs").json().get("collections", [])
if docs:
    selected_doc = st.sidebar.selectbox("📂 Kies document:", docs)

    # Verwijder-knop
    if st.sidebar.button("🗑 Verwijder geselecteerd document"):
        with st.spinner("Bezig met verwijderen..."):
            r = requests.delete(f"http://ai-agent:8001/delete_doc?name={selected_doc}")
        if r.status_code == 200:
            st.sidebar.success(r.json()["message"])
            time.sleep(1)
            st.rerun()  # herlaad app, lijst ververst
        else:
            st.sidebar.error("Verwijderen mislukt.")
else:
    st.sidebar.info("Nog geen documenten geïndexeerd.")


# --- Sidebar: PDF upload ---
st.sidebar.header("📄 Document upload")
uploaded_file = st.sidebar.file_uploader("Upload PDF", type=["pdf"])
if uploaded_file:
    with st.spinner("Bezig met uploaden en indexeren..."):
        r = requests.post(
            "http://ai-agent:8001/upload_pdf",
            files={"file": uploaded_file},
            timeout=300,
        )
    if r.status_code == 200:
        st.sidebar.success(r.json()["message"])
    else:
        st.sidebar.error("Upload mislukt! Controleer backend logs.")

# --- PDF-upload voor medische analyse (buiten form: directe verwerking) ---
if option == "Medische analyse":
    uploaded_patient_pdf = st.file_uploader(
        "📎 Of upload een PDF-formulier (tekst wordt automatisch ingevuld):",
        type=["pdf"],
        key="patient_pdf_uploader",
    )
    if uploaded_patient_pdf is not None:
        reader = pypdf.PdfReader(io.BytesIO(uploaded_patient_pdf.getvalue()))
        extracted = "\n".join(
            page.extract_text() or "" for page in reader.pages
        ).strip()
        if extracted:
            st.session_state.patient_text_area = extracted

# --- Eén formulier voor alle opties ---
med_prompt_input = st.session_state.med_prompt  # default, overschreven in form als optie actief is

with st.form("crm_form", clear_on_submit=False):
    if option == "CRM-vraag":
        user_input = st.text_input("Vraag aan je CRM:")
    elif option == "Tekst schrijven":
        user_input = st.text_area("Schrijfopdracht of onderwerp:")
    elif option == "Bedrijfsdocument":
        user_input = st.text_input("Stel je vraag over het document:")
    elif option == "Medische analyse":
        user_input = st.text_area(
            "Plak hier de patiënttekst (formulier of beschrijving):",
            height=300,
            key="patient_text_area",
            placeholder="Kopieer hier de intake-tekst van de patiënt...",
        )
        with st.expander("✏️ Prompt aanpassen (voor experimenteren)"):
            med_prompt_input = st.text_area(
                "Prompt template — gebruik {text} als plaatshouder voor de patiënttekst:",
                value=st.session_state.med_prompt,
                height=350,
            )
    else:
        user_input = ""

    submitted = st.form_submit_button("Verstuur")

# --- Actie bij klikken op Verstuur ---
if submitted and user_input:
    if option == "Medische analyse":
        st.session_state.med_prompt = med_prompt_input  # bewaar eventuele aanpassing

    with st.spinner("Even geduld, ik haal de data op..."):
        try:
            # Endpoint bepalen
            if option == "Tekst schrijven":
                endpoint = "http://ai-agent:8001/write"
            elif option == "Bedrijfsdocument":
                endpoint = "http://ai-agent:8001/ask_pdf"
            elif option == "Medische analyse":
                endpoint = "http://ai-agent:8001/med"
            else:
                endpoint = "http://ai-agent:8001/chat"

            # Vraag opbouwen
            if option == "Medische analyse":
                payload = {"question": user_input, "prompt_template": med_prompt_input}
            elif option == "Tekst schrijven" and tone:
                payload = {"question": f"Schrijf in een {tone.lower()} stijl: {user_input}"}
            else:
                payload = {"question": user_input}

            # Verstuur
            resp = requests.post(endpoint, json=payload, timeout=180)
            data = resp.json()

            st.divider()
            st.write("🔎 **Debug response:**", data)

            # --- Antwoord tonen ---
            if "answer" in data and data["answer"]:
                st.markdown("### 💬 Antwoord")
                st.write(data["answer"])

            # --- Extra velden ---
            if "analysis" in data and data["analysis"]:
                st.markdown("### 📊 Analyse")
                st.write(data["analysis"])

            if "chart" in data and data["chart"]:
                img_bytes = base64.b64decode(data["chart"])
                st.image(img_bytes, caption="AI-gegenereerde grafiek")

        except Exception as e:
            st.error(f"Er ging iets mis: {e}")

    st.success("Klaar ✅")

# --- Footer ---
st.markdown("---")
st.caption("MB Software – AI Agents voor datagedreven MKB 💡")
