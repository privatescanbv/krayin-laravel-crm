Bekijk de open Dependabot-alerts van deze GitHub-repository.

Werkwijze:

1. Controleer eerst of `gh auth status` werkt.
2. Haal open alerts op met:

   gh api \
     -H "Accept: application/vnd.github+json" \
     /repos/{owner}/{repo}/dependabot/alerts?state=open

3. Prioriteer:
   - critical;
   - high;
   - medium;
   - low.

4. Los eerst alleen Critical en High alerts op.
5. Vermijd major upgrades, tenzij die noodzakelijk zijn om de kwetsbaarheid op te lossen.
6. Controleer per dependency welke manifest- en lockbestanden gewijzigd moeten worden.
7. Draai na iedere wijziging de relevante tests, static analysis en build.
8. Stop bij breaking changes en leg duidelijk uit wat handmatig beoordeeld moet worden.
9. Maak geen commit en push niets zonder expliciete toestemming.
10. Geef aan het einde een overzicht van:
    - opgeloste alerts;
    - resterende alerts;
    - gewijzigde bestanden;
    - testresultaten.
