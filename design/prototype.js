'use strict';

const stages = {
  orders: {
    Privatescan: ['Order bevestigen', 'Ingepland, wacht op akkoord', 'Order akkoord', 'Wachten op uitvoering', 'Uitgevoerd, wachten op rapporten', 'Rapporten vertalen', 'Gewonnen', 'Verloren'],
    Herniapoli: ['Order bevestigen', 'Ingepland', 'Order akkoord', 'Wachten op uitvoering', 'Uitgevoerd, wachten op operatieverslag', 'Rapporten vertalen', 'Gewonnen', 'Verloren'],
  },
  sales: {
    Privatescan: ['In behandeling', 'Met succes afgerond', 'Niet succesvol afgerond'],
    Herniapoli: ['Onderzoek via Privatescan', 'Casus bij arts ter beoordeling', 'Beoordeling gereed', 'Patient bedenktijd', 'Gepland voor aanvullend onderzoek', 'Wachten op zorgverzekeraar', 'Behandeling bevestigen', 'Behandeling gepland', '1e Nazorg', '2e Nazorg', 'Gepland voor fysieke nacontrole', 'Met succes afgerond', 'Niet succesvol afgerond'],
  },
};
const closedStages = ['Gewonnen', 'Verloren', 'Met succes afgerond', 'Niet succesvol afgerond'];
const storageKey = 'privatescan-design-v1';
const names = ['Emma de Vries', 'Lucas Jansen', 'Sophie van Dijk', 'Daan Bakker', 'Julia Visser', 'Lars Smit', 'Sara Meijer', 'Thomas de Boer', 'Eva Mulder', 'Milan Bos', 'Noor Vos', 'Bram Peters'];
const owners = ['Marieke', 'Thomas', 'Sanne'];
const examinations = ['MRI total body', 'MRI rug', 'Preventief gezondheidsonderzoek', 'MRI knie', 'MRI hoofd', 'MRI nek'];
function seed() {
  return ['orders', 'sales'].flatMap(type => Array.from({ length: type === 'orders' ? 19 : 25 }, (_, i) => {
    const pipeline = i % 6 === 5 ? 'Herniapoli' : 'Privatescan';
    const pipelineStages = stages[type][pipeline];
    return {
      id: `${type}-${i + 1}`, type, number: `${type === 'orders' ? '20260' : 'S-2026-'}${1903 - i}`,
      title: examinations[i % 6], person: names[i % names.length], email: `voorbeeld${i + 1}@example.com`,
      phone: '06 1234 5678', pipeline,
      stage: pipelineStages[i % pipelineStages.length], owner: owners[i % 3], amount: 695 + (i % 5) * 300,
      payment: i % 3 === 0 ? 'Volledig betaald' : i % 3 === 1 ? 'Niet betaald' : 'Gedeeltelijk betaald',
      date: `2026-11-${String(3 + i % 20).padStart(2, '0')}`, organization: '', salesId: type === 'orders' ? `sales-${i % 25 + 1}` : '',
      combine: true, description: 'Voorbeeldcasus voor het ontwerp. De klant wil graag meer informatie over het onderzoek.',
    };
  }));
}
let records;
try { const saved = JSON.parse(localStorage.getItem(storageKey)); records = Array.isArray(saved) && saved.every(r => r && stages[r.type]?.[r.pipeline] && typeof r.id === 'string') ? saved : seed(); }
catch { records = seed(); }
// Preserve locally edited records while correcting stages from the earlier prototype.
for (const r of records) {
  if (r.type === 'sales') {
    if (r.stage === 'Gewonnen') r.stage = 'Met succes afgerond';
    if (r.stage === 'Verloren') r.stage = 'Niet succesvol afgerond';
  } else if (r.pipeline === 'Herniapoli') {
    if (r.stage === 'Ingepland, wacht op akkoord') r.stage = 'Ingepland';
    if (r.stage === 'Uitgevoerd, wachten op rapporten') r.stage = 'Uitgevoerd, wachten op operatieverslag';
  }
  if (!stages[r.type][r.pipeline].includes(r.stage)) r.stage = stages[r.type][r.pipeline][0];
}
let type = location.hash.startsWith('#sales') ? 'sales' : 'orders';
let pipeline = 'Privatescan';
let view = 'board';
let currentId = null;
let pendingDelete = null;
let toastTimer;
const $ = selector => document.querySelector(selector);
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const euro = value => new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(value);
const date = value => value ? new Intl.DateTimeFormat('nl-NL', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${value}T12:00:00`)) : 'Niet gepland';
function notify(message) { clearTimeout(toastTimer); $('#toast').textContent = message; $('#toast').hidden = false; toastTimer = setTimeout(() => { $('#toast').hidden = true; }, 3500); }
function persist() {
  try { localStorage.setItem(storageKey, JSON.stringify(records)); return true; }
  catch { notify('Opslaan in deze browser lukt niet. Wijzigingen blijven tot je deze pagina sluit.'); return false; }
}
function filtered() {
  const query = $('#search').value.toLocaleLowerCase('nl');
  return records.filter(r => r.type === type && r.pipeline === pipeline && ($('#closed').checked || !closedStages.includes(r.stage)) && (!$('#owner').value || r.owner === $('#owner').value) && `${r.person} ${r.title} ${r.number}`.toLocaleLowerCase('nl').includes(query));
}
function render() {
  const title = type === 'orders' ? 'Orders' : 'Sales';
  document.title = `${title} · Privatescan prototype`;
  $('#title').textContent = $('#crumb').textContent = title;
  $('#subtitle').textContent = type === 'orders' ? 'Van bevestiging tot rapportage.' : 'Van interesse tot een geplande behandeling.';
  $('#create').textContent = type === 'orders' ? '＋ Nieuwe order' : '＋ Nieuwe sales';
  document.querySelectorAll('[data-nav]').forEach(a => { a.classList.toggle('active', a.dataset.nav === type); if (a.dataset.nav === type) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current'); });
  $('#pipelines').innerHTML = ['Privatescan', 'Herniapoli'].map(p => `<button data-pipeline="${p}" class="${pipeline === p ? 'active' : ''}" aria-pressed="${pipeline === p}">${p}</button>`).join('');
  $('#board-view').classList.toggle('active', view === 'board'); $('#list-view').classList.toggle('active', view === 'list');
  $('#board-view').setAttribute('aria-pressed', view === 'board'); $('#list-view').setAttribute('aria-pressed', view === 'list');
  const rows = filtered();
  $('#count').textContent = `${rows.length} ${type === 'orders' ? 'orders' : 'sales'} · ${euro(rows.reduce((sum, r) => sum + Number(r.amount), 0))}`;
  if (view === 'board') {
    $('#content').innerHTML = `<div class="board">${stages[type][pipeline].filter(s => $('#closed').checked || !closedStages.includes(s)).map(stage => {
      const items = rows.filter(r => r.stage === stage);
      return `<section class="column"><header class="column-head"><div class="column-title"><strong>${esc(stage)}</strong><span class="badge-count">${items.length}</span></div><small>${euro(items.reduce((sum, r) => sum + Number(r.amount), 0))}</small></header><div class="cards">${items.length ? items.map(card).join('') : '<div class="empty">Geen resultaten in deze fase</div>'}</div></section>`;
    }).join('')}</div>`;
  } else {
    $('#content').innerHTML = `<div class="table-wrap"><table><thead><tr><th>Nummer</th><th>Contactpersoon</th><th>Onderzoek</th><th>Fase</th><th>Medewerker</th><th>Waarde</th><th></th></tr></thead><tbody>${rows.length ? rows.map(r => `<tr><td>${esc(r.number)}</td><td><strong>${esc(r.person)}</strong></td><td>${esc(r.title)}</td><td>${esc(r.stage)}</td><td>${esc(r.owner)}</td><td>${euro(r.amount)}</td><td><button data-open="${esc(r.id)}">Bekijken</button></td></tr>`).join('') : '<tr><td colspan="7" class="empty">Geen resultaten. Pas je zoekopdracht of filters aan.</td></tr>'}</tbody></table></div>`;
  }
}
function card(r) {
  return `<button class="card" data-open="${esc(r.id)}"><div class="card-ref"><span>${esc(r.number)}</span><span>${date(r.date)}</span></div><h3>${esc(r.person)}</h3><p>${esc(r.title)}</p>${type === 'orders' ? `<span class="status ${r.payment === 'Volledig betaald' ? 'paid' : ''}">${esc(r.payment)}</span>` : ''}<div class="card-bottom"><span class="mini-avatar">${esc(r.owner[0])}</span><span>${esc(r.owner)}</span><span class="money">${euro(r.amount)}</span></div></button>`;
}
function options(values, selected) { return values.map(v => `<option value="${esc(v)}" ${v === selected ? 'selected' : ''}>${esc(v)}</option>`).join(''); }
function input(label, name, value, inputType = 'text', required = false, wide = false) {
  return `<label class="${wide ? 'wide' : ''}">${label}${required ? ' *' : ''}<input name="${name}" type="${inputType}" value="${esc(value)}" ${required ? 'required' : ''} ${inputType === 'number' ? 'min="0" step="0.01"' : ''} ${inputType === 'text' ? 'maxlength="200"' : ''}></label>`;
}
function select(label, name, values, selected) { return `<label>${label}<select name="${name}">${options(values, selected)}</select></label>`; }
function openRecord(id, editing = false) {
  const record = records.find(r => r.id === id);
  location.hash = `${record?.type || type}/${id || 'new'}${id ? (editing ? '/edit' : '/algemeen') : ''}`;
}
function renderEditor(id) {
  currentId = id;
  const r = records.find(r => r.id === id) || { title: '', person: '', email: '', phone: '', stage: stages[type][pipeline][0], pipeline, owner: 'Marieke', amount: '', payment: 'Niet betaald', date: '', description: '', organization: '', salesId: '', combine: true };
  $('#record-number').innerHTML = `<a href="#${type}">${type === 'orders' ? 'Orders' : 'Sales'}</a> / ${id ? esc(r.number) + ' / Bewerken' : 'Aanmaken'}`;
  $('#dialog-title').textContent = `${type === 'orders' ? 'Order' : 'Sales'} ${id ? 'bewerken' : 'aanmaken'}`;
  $('#fields').innerHTML = `<div class="form-grid">${input('Titel / onderzoek', 'title', r.title, 'text', true, true)}${input('Contactpersoon', 'person', r.person, 'text', true)}${select('Toegewezen medewerker', 'owner', owners, r.owner)}${input('E-mailadres', 'email', r.email, 'email')}${input('Telefoon', 'phone', r.phone, 'tel')}<h3 class="form-section">Traject</h3>${select('Pipeline', 'pipeline', ['Privatescan', 'Herniapoli'], r.pipeline)}${select('Fase', 'stage', stages[type][r.pipeline], r.stage)}${input('Waarde (€)', 'amount', r.amount, 'number', true)}${input('Onderzoeksdatum', 'date', r.date, 'date')}${type === 'orders' ? `${select('Betaling', 'payment', ['Niet betaald', 'Gedeeltelijk betaald', 'Volledig betaald', 'Niet van toepassing'], r.payment)}<label>Gekoppelde sales<select name="salesId"><option value="">Geen sales gekoppeld</option>${records.filter(item => item.type === 'sales').map(item => `<option value="${esc(item.id)}" ${item.id === r.salesId ? 'selected' : ''}>${esc(item.number)} · ${esc(item.person)}</option>`).join('')}</select></label>${input('Organisatie (optioneel)', 'organization', r.organization)}${select('Orders combineren', 'combine', ['Ja', 'Nee'], r.combine ? 'Ja' : 'Nee')}` : ''}<label class="wide">Omschrijving<textarea name="description" rows="4" maxlength="5000">${esc(r.description)}</textarea></label></div>`;
  if (type === 'orders') {
    $('#fields .form-grid').insertAdjacentHTML('beforeend', input('Factuurnummer', 'invoice', r.invoice || '') + input('Tijd eerste onderzoek', 'time', r.time || '', 'time'));
    $('#fields').insertAdjacentHTML('afterbegin', '<div class="editor-tabs"><button type="button" class="active" data-editor-tab="details">Details</button><button type="button" data-editor-tab="checks">Checks</button></div>');
    $('#fields').insertAdjacentHTML('beforeend', `<div class="editor-checks" hidden><h3>Checks</h3>${(r.checks || []).map(c => `<p>${c.done ? '✓' : '○'} ${esc(c.name)}</p>`).join('') || '<p class="muted">Geen checks toegevoegd aan deze order.</p>'}<p class="muted">Checks toevoegen en afvinken kan op de detailpagina.</p></div>`);
  }
  const linkedId = new URLSearchParams(location.hash.split('?')[1] || '').get('sales');
  const linked = records.find(item => item.id === linkedId && item.type === 'sales');
  if (!id && type === 'orders' && linked) {
    for (const name of ['person', 'email', 'phone', 'pipeline']) $('#record-form').elements[name].value = linked[name];
    $('#record-form').elements.salesId.value = linked.id;
    updateEditorStages();
  }
}

$('#record-form').addEventListener('submit', event => {
  event.preventDefault();
  const form = event.currentTarget;
  for (const name of ['title', 'person']) {
    const field = form.elements[name]; field.setCustomValidity(field.value.trim() ? '' : 'Vul dit veld in.');
    if (!field.reportValidity()) return;
  }
  const values = Object.fromEntries(new FormData(form));
  if (!stages[type][values.pipeline]?.includes(values.stage)) {
    form.elements.stage.setCustomValidity('Kies een fase uit deze pipeline.');
    form.elements.stage.reportValidity();
    return;
  }
  values.title = values.title.trim(); values.person = values.person.trim(); values.amount = Number(values.amount); values.combine = values.combine === 'Ja';
  const existing = records.find(r => r.id === currentId);
  if (existing) Object.assign(existing, values);
  else records.unshift({ ...values, type, id: `${type}-${crypto.randomUUID ? crypto.randomUUID() : Date.now()}`, number: `${type === 'orders' ? 'O' : 'S'}-${Date.now().toString().slice(-7)}` });
  pipeline = values.pipeline;
  if (closedStages.includes(values.stage)) $('#closed').checked = true;
  $('#search').value = ''; $('#owner').value = '';
  const saved = persist(); openRecord(existing?.id || records[0].id); if (saved) notify(existing ? 'Wijzigingen opgeslagen' : `${type === 'orders' ? 'Order' : 'Sales'} aangemaakt`);
});
$('#record-form').addEventListener('input', event => event.target.setCustomValidity?.(''));
function updateEditorStages() {
  const form = $('#record-form');
  const available = stages[type][form.elements.pipeline.value];
  const selected = available.includes(form.elements.stage.value) ? form.elements.stage.value : available[0];
  form.elements.stage.innerHTML = options(available, selected);
  form.elements.stage.setCustomValidity('');
}
$('#record-form').addEventListener('change', event => {
  if (event.target.name === 'pipeline') updateEditorStages();
});
document.querySelectorAll('[data-close]').forEach(button => { button.onclick = () => { location.hash = currentId ? `${type}/${currentId}/algemeen` : type; }; });
function askDelete(id) {
  pendingDelete = id;
  $('#confirm-text').textContent = `Wil je ${records.find(r => r.id === id).person} verwijderen? Dit verwijdert alleen het record uit dit prototype.`;
  $('#confirm-dialog').showModal();
}
$('#cancel-delete').onclick = () => $('#confirm-dialog').close();
$('#confirm-delete').onclick = () => {
  if (pendingDelete === 'all') records = seed();
  else { records = records.filter(r => r.id !== pendingDelete); records.forEach(r => { if (r.salesId === pendingDelete) r.salesId = ''; }); }
  const saved = persist(); $('#confirm-dialog').close(); location.hash = type; renderRoute(); if (saved) notify(pendingDelete === 'all' ? 'Voorbeelddata hersteld' : 'Record verwijderd'); pendingDelete = null;
};
$('#reset').onclick = () => { pendingDelete = 'all'; $('#confirm-text').textContent = 'Alle wijzigingen in dit prototype worden vervangen door de oorspronkelijke voorbeelddata.'; $('#confirm-dialog').showModal(); };
$('#create').onclick = () => openRecord(null, true);
$('#content').onclick = event => { const button = event.target.closest('[data-open]'); if (button) openRecord(button.dataset.open); };
$('#pipelines').onclick = event => { const button = event.target.closest('[data-pipeline]'); if (button) { pipeline = button.dataset.pipeline; render(); } };
$('#search').oninput = render; $('#owner').onchange = render; $('#closed').onchange = render;
$('#board-view').onclick = () => { view = 'board'; render(); }; $('#list-view').onclick = () => { view = 'list'; render(); };
function renderRoute() {
  const [module, id, section = 'algemeen'] = location.hash.slice(1).split('?')[0].split('/');
  type = module === 'sales' ? 'sales' : 'orders';
  const record = records.find(r => r.id === id && r.type === type);
  const editing = id === 'new' || (record && section === 'edit');
  $('#overview').hidden = !!record || editing;
  $('#record-page').hidden = !record || editing;
  $('#editor-page').hidden = !editing;
  document.body.classList.toggle('record-layout', !!record && !editing);
  currentId = record?.id || null;
  document.querySelectorAll('[data-nav]').forEach(a => {
    a.classList.toggle('active', a.dataset.nav === type);
    if (a.dataset.nav === type) a.setAttribute('aria-current', 'page');
    else a.removeAttribute('aria-current');
  });
  if (editing) renderEditor(record?.id || null);
  else if (record) { pipeline = record.pipeline; renderDetail(record, section); }
  else render();
  if (record || editing) {
    document.title = `${record?.person || 'Aanmaken'} · ${type === 'orders' ? 'Orders' : 'Sales'} · Privatescan`;
    $('#record-page h2, #dialog-title').setAttribute('tabindex', '-1');
  }
  window.scrollTo(0, 0);
}
function icon(name) {
  const paths = {
    mail: '<rect x="3" y="5" width="18" height="14" rx="4"/><path d="m5 7 7 5 7-5"/>',
    phone: '<path d="m7 3 3 5-3 3c2 3 3 4 6 6l3-3 5 3c-1 5-4 6-9 3S1 9 3 5z"/>',
    user: '<rect x="3" y="3" width="18" height="18" rx="4"/><circle cx="12" cy="9" r="3"/><path d="M7 19v-2c0-4 10-4 10 0v2"/>',
    file: '<path d="M8 3h8l4 4v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM15 3v6h5M8 13h8M8 17h5"/>',
    note: '<rect x="6" y="3" width="14" height="17" rx="3"/><path d="m6 7-3 1v13l12-1M10 8h6M10 12h6"/>',
    activity: '<rect x="5" y="3" width="14" height="18" rx="3"/><path d="M9 3v3h6V3M9 11h6M9 15h5"/>',
    edit: '<path d="m4 16 12-12 4 4L8 20H4v-4zM13 7l4 4"/>',
    trash: '<path d="M4 6h16M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7M14 10v7"/>',
    link: '<path d="m10 8 2-2a5 5 0 0 1 7 7l-2 2M14 16l-2 2a5 5 0 0 1-7-7l2-2M8 16l8-8"/>',
    orders: '<path d="M4 4h16v17H4zM8 4V2h8v2M8 9h8M8 13h8M8 17h5"/>',
    headset: '<path d="M4 14v-3a8 8 0 0 1 16 0v3M4 12H2v7h4v-7zM20 12h2v7h-4v-7z"/>',
  };
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || paths.file}</svg>`;
}
function panel(title, body, actions = '', className = '') {
  return `<section class="panel ${className}"><div class="panel-heading"><h3>${title}</h3>${actions}</div><div class="panel-body">${body}</div></section>`;
}
function readonlyField(label, value) {
  return `<div class="readonly-field"><span>${label}</span><div>${esc(value || '—')}</div></div>`;
}
function personPanel(r) {
  return panel('Persoon', `<p class="muted">${esc(r.person)} gekoppeld</p>${type === 'sales' ? '<p class="match">Match: <progress value="100" max="100"></progress> 100%</p>' : ''}<div class="person-fields">${readonlyField('Naam contactpersoon', r.person)}${readonlyField('Telefoonnummer', r.phone)}${readonlyField('E-mailadres', r.email)}</div><div class="person-actions"><button data-edit-record aria-label="Contactgegevens bewerken">${icon('edit')}</button><a href="mailto:${esc(r.email)}" aria-label="E-mail contactpersoon">${icon('mail')}</a></div>`, '', 'person-panel');
}
function renderDetail(r, section) {
  const order = r.type === 'orders';
  const sections = order ? [['algemeen', 'Algemeen', 'user'], ['activiteiten', 'Activiteiten', 'activity'], ['afletteren', 'Afletteren', 'headset'], ['betalingen', 'Betalingen Klant', 'headset'], ['anamnese', 'Anamnese', 'note']] : [['algemeen', 'Algemeen', 'user'], ['activiteiten', 'Activiteiten', 'activity'], ['anamnese', 'Anamnese', 'note'], ['orders', 'Orders', 'orders'], ['marketing', 'Marketing', 'headset']];
  if (!sections.some(([key]) => key === section)) section = 'algemeen';
  const actions = [['mail', 'E-mail'], ...(order ? [['link', 'Rapportage']] : []), ['file', 'Bestand'], ['note', 'Notitie'], ['activity', 'Activiteit'], ...(order ? [['link', 'Lead']] : [])];
  $('#record-page').innerHTML = `<div class="record-grid">
    <aside class="contact-panel panel">
      <div class="contact-summary"><div class="record-breadcrumb"><a href="#${type}">${order ? 'Orders' : 'Sales'}</a> / ${esc(r.number)}</div>
        <div class="contact-title"><h2>${esc(r.person)}</h2><span class="stage-pill">${esc(r.stage)}</span></div>
        <div class="contact-info">${!order ? `<div>${icon('user')} <span>${esc(r.birthdate || '12-05-1980')} <span class="muted">(voorbeeld)</span></span></div>` : ''}<div>${icon('phone')}<a href="tel:${esc(r.phone)}">${esc(r.phone || 'Geen telefoonnummer')}</a></div><div>${icon('mail')}<a href="mailto:${esc(r.email)}">${esc(r.email || 'Geen e-mailadres')}</a></div></div>
        <div class="contact-actions">${actions.map(([symbol, label]) => `<button class="action-tile tile-${symbol}" data-record-action="${label}">${icon(symbol)}<span>${label}</span></button>`).join('')}</div>
        ${!order ? `<button class="related-sales" data-record-action="Nieuwe ${r.pipeline === 'Herniapoli' ? 'Preventie' : 'Herniapoli'} Sales aanmaken">Nieuwe ${r.pipeline === 'Herniapoli' ? 'Preventie' : 'Herniapoli'} Sales aanmaken</button>` : ''}
      </div>
      <nav class="section-nav" aria-label="Recordsecties">${sections.map(([key, label, symbol]) => `<a href="#${type}/${esc(r.id)}/${key}" class="${section === key ? 'active' : ''}" ${section === key ? 'aria-current="page"' : ''}>${icon(symbol)}<span>${label}</span>${key === 'activiteiten' ? `<b>${(r.activities || []).length}</b>` : ''}</a>`).join('')}</nav>
      <dl class="record-meta">${[['Toegewezen aan:', r.owner], ['Afdeling:', r.pipeline], ['Aangemaakt:', '03-11-2026'], ['Laatst gewijzigd:', '03-11-2026']].map(([k, v]) => `<div><dt>${k}</dt><dd>${esc(v)}</dd></div>`).join('')}</dl>
    </aside>
    <div class="record-center">${section === 'algemeen' ? generalDetail(r) : sectionDetail(r, section)}</div>
    <aside class="ai-panel"><button class="collapse-ai" aria-label="Rechterkolom inklappen" aria-expanded="true">›</button><div class="ai-content"><div class="ai-title"><h2>AI-samenvatting</h2><button data-record-action="AI vernieuwen">⟳ Vernieuwen</button></div><p class="muted">Geen AI-samenvatting beschikbaar in dit prototype.</p><div class="ai-corrections"><strong>AI-correcties</strong><p>0 actieve correcties</p></div></div></aside>
  </div>`;
}
function generalDetail(r) {
  const order = r.type === 'orders';
  const label = order ? 'Order' : 'Sales';
  const toolbar = `<div class="record-actions">${order ? '<button disabled title="Bevestiging en planning vallen buiten dit prototype">Afspraak bevestigen</button><button data-record-action="Resource Planner">Resource Planner</button>' : ''}<button data-edit-record>${icon('edit')} Bewerk ${label.toLowerCase()}</button><button data-delete-record class="danger">${icon('trash')} ${label} verwijderen</button></div>`;
  const steps = stages[r.type][r.pipeline].filter(s => !closedStages.includes(s));
  let html = `<section class="panel record-toolbar"><h3>Algemene informatie ${label}</h3>${toolbar}</section><nav class="stage-navigation" aria-label="Fase wijzigen">${steps.map(stage => `<button data-stage="${esc(stage)}" class="${r.stage === stage ? 'active' : ''}" ${r.stage === stage ? 'aria-current="step"' : ''}>${esc(stage)}</button>`).join('')}<label class="terminal-stage"><select aria-label="Gewonnen of verloren" data-terminal-stage><option value="">Gewonnen/Verloren</option>${options(stages[r.type][r.pipeline].filter(s => closedStages.includes(s)), r.stage)}</select></label></nav>`;
  if (order) {
    html += panel('Checks', (r.checks || []).length ? `<div class="checks-list">${r.checks.map((c, i) => `<label><input type="checkbox" data-check="${i}" ${c.done ? 'checked' : ''}> ${esc(c.name)}</label>`).join('')}</div>` : '<p class="muted">Geen checks toegevoegd aan deze order.</p>', '<button data-record-action="Checks toevoegen">'+icon('edit')+' Checks toevoegen</button>');
    html += panel('≡ &nbsp; Order gegevens', `<dl class="order-data">${[['Ordernummer', r.number], ['Titel', r.title], ['Totaalprijs', euro(r.amount)], ['Betaling klant', r.payment], ['Totale inkoopprijs', euro(0)], ['Inkoop status', 'Geen inkoopprijs'], ['Eerste onderzoek', date(r.date)], ['Gecombineerde order', r.combine ? 'Ja' : 'Nee'], ['Factuurnummer', r.invoice || '—'], ['Zakelijk', r.organization ? 'Ja' : 'Nee']].map(([k, v]) => `<div><dt>${k}</dt><dd>${esc(v)}</dd></div>`).join('')}</dl>`, '<span class="updated">▧ &nbsp; Laatst bijgewerkt: 03 Nov 2026</span>');
    html += panel('AFB status', '<div class="afb-state"><span class="error-pill">× Niet klaar voor verzending</span><ul><li>Geen kliniekafdelingen gekoppeld aan order items</li><li>Verzending is niet gekoppeld in het prototype</li></ul></div>', '<button data-record-action="Handmatig verzenden">'+icon('mail')+' Handmatig verzenden</button>');
    const linked = records.find(item => item.id === r.salesId);
    html += panel('Gekoppelde Sales & Lead', `<dl class="linked-records"><dt>Sales</dt><dd>${linked ? `<a href="#sales/${esc(linked.id)}/algemeen">${icon('link')} ${esc(linked.person)}</a>` : 'Geen sales gekoppeld'}</dd><dt>Lead</dt><dd>${esc(r.person)}</dd></dl>`);
    html += personPanel(r);
  } else {
    if (r.pipeline === 'Herniapoli') html += `<section class="panel compact-row"><span>Uitkomst beoordeling</span><strong>${esc(r.assessment || 'Nog niet ingevuld')}</strong></section><section class="panel compact-row"><span>Aanvullend onderzoek vereist</span><strong>${esc(r.additionalResearch || 'Nee')}</strong></section>`;
    html += `<section class="panel description-panel">${esc(r.description || '')}</section>`;
    const parts = r.person.split(' ');
    html += panel('≡ &nbsp; Lead gegevens', `<div class="lead-columns"><div><h4>${icon('user')} Identiteit</h4><div class="identity-grid">${readonlyField('Aanhef', r.salutation || 'Mevr.')}${readonlyField('Voornaam', parts[0])}${readonlyField('Tussenvoegsel', '')}${readonlyField('Achternaam', parts.slice(1).join(' '))}${readonlyField('Tussenvoegsel', '')}${readonlyField('Aangetrouwde achternaam', '')}<div class="wide">${readonlyField('Geboortedatum', r.birthdate || '12-05-1980')}</div></div></div><div><h4>${icon('link')} Adresgegevens</h4>${readonlyField('Straat en huisnummer', r.street || 'Voorbeeldstraat 12')}${readonlyField('Postcode', r.postcode || '1234 AB')}${readonlyField('Woonplaats', r.city || 'Utrecht')}${readonlyField('Land', 'Nederland')}</div><div><h4>${icon('phone')} Contact & identificatie</h4>${readonlyField('Telefoonnummer', r.phone)}${readonlyField('E-mailadres', r.email)}${readonlyField('Burgerservicenummer (BSN)', '')}</div></div>`, '<span class="updated">Laatst bijgewerkt: 03 Nov 2026</span>');
    html += `<div class="person-grid">${personPanel(r)}<button class="attach-person" data-record-action="Persoon koppelen">＋<span>Persoon koppelen</span></button></div>`;
  }
  return html;
}
function sectionDetail(r, section) {
  if (section === 'orders') {
    const orders = records.filter(item => item.salesId === r.id);
    const orderTable = closed => `<div class="table-wrap"><table><thead><tr><th>Ordernummer</th><th>Titel</th><th>Fase</th><th>Totaalprijs</th></tr></thead><tbody>${orders.filter(item => closedStages.includes(item.stage) === closed).map(item => `<tr><td><a href="#orders/${esc(item.id)}/algemeen">${esc(item.number)}</a></td><td>${esc(item.title)}</td><td>${esc(item.stage)}</td><td>${euro(item.amount)}</td></tr>`).join('') || '<tr><td colspan="4" class="muted">Geen orders gevonden</td></tr>'}</tbody></table></div>`;
    return panel('Orders', '<p class="muted">Openstaande orders staan bovenaan. Afgeronde orders staan onder “Afgerond”.</p>', '<button data-new-linked-order class="primary">Nieuwe Order</button>') + panel('Open / lopend', orderTable(false)) + `<details class="panel record-disclosure"><summary>Afgerond</summary>${orderTable(true)}</details>`;
  }
  if (section === 'activiteiten') return panel('Activiteiten', `<div class="activity-tabs"><strong>Alles</strong><span>Notities</span><span>Activiteiten</span><span>Bestanden</span><span>E-mails</span></div>${(r.activities || []).length ? r.activities.map(a => `<article class="activity-entry">${icon('note')}<div><strong>${esc(a.kind)}</strong><p>${esc(a.text)}</p><small>03 november 2026 · ${esc(r.owner)}</small></div></article>`).join('') : '<p class="empty">Geen activiteiten beschikbaar.</p>'}`, '<button data-record-action="Notitie">＋ Notitie toevoegen</button>');
  if (section === 'betalingen') {
    const paid = r.payment === 'Volledig betaald' ? Number(r.amount) : r.payment === 'Gedeeltelijk betaald' ? Number(r.amount) / 2 : 0;
    return panel('Klant betalingen', '<p class="muted">Aanbetaling, kliniekbetaling en terugbetalingen voor deze order.</p>') + `<div class="payment-summary">${[['Totaal te betalen', euro(r.amount)], ['Reeds betaald', euro(paid)], ['Nog open', euro(Number(r.amount) - paid)], ['Betaalstatus', r.payment]].map(([k, v]) => `<div class="panel"><span>${k}</span><strong>${esc(v)}</strong></div>`).join('')}</div>` + panel('Betalingen', '<p class="muted">Voorbeeldweergave op basis van de betaalstatus. Er zijn geen echte transacties gekoppeld.</p>', '<button data-edit-record>Betaalstatus bewerken</button>');
  }
  if (section === 'afletteren') return panel('Afletteren', '<div class="table-wrap"><table><thead><tr><th>Onderzoek</th><th>Inkoopprijs</th><th>Factuurbedrag</th><th>Verschil</th><th>Status</th></tr></thead><tbody><tr><td colspan="5" class="empty">Geen inkoopregels gekoppeld aan deze order.</td></tr></tbody></table></div>');
  if (section === 'anamnese') return panel('Anamnese', `<p>${esc(r.person)}</p><div class="empty">Er is nog geen anamnese beschikbaar voor deze persoon.</div>`);
  return panel('Marketing', `<dl class="order-data"><div><dt>Bron</dt><dd>Website</dd></div><div><dt>Afdeling</dt><dd>${esc(r.pipeline)}</dd></div><div><dt>Campagne</dt><dd>—</dd></div><div><dt>Toestemming marketing</dt><dd>Niet vastgelegd</dd></div></dl>`);
}
$('#record-page').addEventListener('click', event => {
  const r = records.find(item => item.id === currentId);
  if (!r) return;
  if (event.target.closest('[data-edit-record]')) openRecord(r.id, true);
  if (event.target.closest('[data-cancel-action]')) renderDetail(r, 'algemeen');
  if (event.target.closest('[data-delete-record]')) askDelete(r.id);
  const stage = event.target.closest('[data-stage]');
  if (stage) { r.stage = stage.dataset.stage; persist(); renderDetail(r, 'algemeen'); }
  if (event.target.closest('.collapse-ai')) {
    const grid = $('#record-page .record-grid'); grid.classList.toggle('ai-collapsed');
    const button = $('#record-page .collapse-ai'); button.setAttribute('aria-expanded', !grid.classList.contains('ai-collapsed')); button.setAttribute('aria-label', grid.classList.contains('ai-collapsed') ? 'Rechterkolom uitklappen' : 'Rechterkolom inklappen'); button.textContent = grid.classList.contains('ai-collapsed') ? '‹' : '›';
  }
  const action = event.target.closest('[data-record-action]');
  if (action) showRecordAction(r, action.dataset.recordAction);
  if (event.target.closest('[data-new-linked-order]')) { location.hash = `orders/new?sales=${encodeURIComponent(r.id)}`; }
});
$('#record-page').addEventListener('change', event => {
  const r = records.find(item => item.id === currentId);
  if (event.target.matches('[data-terminal-stage]') && event.target.value) { r.stage = event.target.value; persist(); renderDetail(r, 'algemeen'); }
  if (event.target.matches('[data-check]')) { r.checks[Number(event.target.dataset.check)].done = event.target.checked; persist(); }
});
function showRecordAction(r, action) {
  if (['Notitie', 'Activiteit', 'Checks toevoegen'].includes(action)) {
    const center = $('#record-page .record-center');
    center.innerHTML = panel(action, `<form id="quick-action"><label>${action === 'Checks toevoegen' ? 'Naam check' : 'Omschrijving'}<textarea name="text" required maxlength="5000" rows="4"></textarea></label><div class="quick-action-buttons"><button type="button" data-cancel-action>Annuleren</button><button class="primary">Opslaan</button></div></form>`) + sectionDetail(r, 'activiteiten');
    $('#quick-action textarea').focus();
    $('#quick-action').onsubmit = event => {
      event.preventDefault(); const text = new FormData(event.currentTarget).get('text').trim();
      if (!text) { $('#quick-action textarea').setCustomValidity('Vul een omschrijving in.'); $('#quick-action textarea').reportValidity(); return; }
      if (action === 'Checks toevoegen') { r.checks ||= []; r.checks.push({ name: text, done: false }); }
      else { r.activities ||= []; r.activities.unshift({ kind: action, text }); }
      persist();
      const targetSection = action === 'Checks toevoegen' ? 'algemeen' : 'activiteiten';
      const targetHash = `#${type}/${r.id}/${targetSection}`;
      if (location.hash === targetHash) renderDetail(r, targetSection);
      else location.hash = targetHash;
    };
    $('#quick-action textarea').oninput = event => event.target.setCustomValidity('');
  } else {
    notify(`${action}: deze koppeling valt buiten het statische prototype.`);
  }
}
$('#fields').addEventListener('click', event => {
  const tab = event.target.closest('[data-editor-tab]');
  if (!tab) return;
  $('#fields .form-grid').hidden = tab.dataset.editorTab !== 'details';
  $('#fields .editor-checks').hidden = tab.dataset.editorTab !== 'checks';
  document.querySelectorAll('[data-editor-tab]').forEach(button => button.classList.toggle('active', button === tab));
});
window.onhashchange = renderRoute;
renderRoute();
