import streamlit as st
import requests
import base64

st.title("CRM AI Agent")

user_input = st.text_input("Vraag aan je CRM:")

if st.button("Verstuur"):
    with st.spinner("Even geduld, ik haal de data op..."):
        try:
            resp = requests.post("http://ai-agent:8001/chat", json={"question": user_input}, timeout=120)
            data = resp.json()

            # Debug: toon raw response
            st.write("🔎 Debug response:", data)

            # Tekstantwoord
            if "answer" in data and data["answer"]:
                st.write("Antwoord:", data["answer"])

            # Analyse
            if "analysis" in data and data["analysis"]:
                st.write("Analyse:", data["analysis"])

            # Grafiek
            if "chart" in data and data["chart"]:
                img_bytes = base64.b64decode(data["chart"])
                st.image(img_bytes, caption="AI gegenereerde grafiek")

        except Exception as e:
            st.error(f"Er ging iets mis: {e}")

    st.success("Klaar ✅")
