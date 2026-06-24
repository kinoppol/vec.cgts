/* ============================================================
   admin-lookup.jsx — จัดการรายการอ้างอิง (กลุ่มงาน / ตำแหน่ง)
   ============================================================ */

const LOOKUP_CATS = [
  { cat: 'group_name', label: 'ชื่อกลุ่มงาน',  icon: 'users',
    desc: 'ใช้เป็นตัวเลือกกลุ่มงานในฟอร์มผู้ใช้และนิติกร' },
  { cat: 'job_title',  label: 'ชื่อตำแหน่ง',   icon: 'gavel',
    desc: 'ใช้เป็นตัวเลือกตำแหน่งงานในฟอร์มผู้ใช้และนิติกร' },
];

/* ── section เดียวสำหรับแต่ละ category ─────────────────── */
function LookupSection({ cat, label, icon, desc }) {
  const [items,   setItems]  = React.useState([]);
  const [loading, setLoad]   = React.useState(true);
  const [newName, setNewName]= React.useState('');
  const [adding,  setAdding] = React.useState(false);
  const [editId,  setEditId] = React.useState(null);
  const [editVal, setEditVal]= React.useState('');
  const [busy,    setBusy]   = React.useState('');

  React.useEffect(() => {
    api.getLookups(cat).then(data => { setItems(data); setLoad(false); });
  }, [cat]);

  async function addItem() {
    if (!newName.trim()) return;
    setAdding(true);
    try {
      const item = await api.createLookup({ category: cat, name: newName.trim() });
      setItems(prev => [...prev, item]);
      setNewName('');
    } catch(e) { alert(e.message); }
    finally { setAdding(false); }
  }

  async function saveEdit(id) {
    if (!editVal.trim()) return;
    setBusy(id);
    try {
      const item = await api.updateLookup(id, { name: editVal.trim() });
      setItems(prev => prev.map(x => x.id === id ? item : x));
      setEditId(null);
    } catch(e) { alert(e.message); }
    finally { setBusy(''); }
  }

  async function remove(id, name) {
    if (!confirm(`ลบรายการ "${name}" ?`)) return;
    try {
      await api.deleteLookup(id);
      setItems(prev => prev.filter(x => x.id !== id));
    } catch(e) { alert(e.message); }
  }

  return (
    <div className="card" style={{flex:'1 1 320px',minWidth:0}}>
      <div className="card-h">
        <div className="vcenter" style={{gap:8}}>
          <Icon name={icon} style={{width:18,height:18,color:'var(--maroon)'}}/>
          <h3 style={{fontSize:15}}>{label}</h3>
        </div>
        <span className="badge">{items.length} รายการ</span>
      </div>
      <div style={{padding:'6px 16px 10px',fontSize:12,color:'var(--ink-3)'}}>{desc}</div>

      {loading
        ? <div style={{padding:'16px',color:'var(--ink-3)',fontSize:13}}>กำลังโหลด…</div>
        : (
        <ul style={{margin:0,padding:0,listStyle:'none'}}>
          {items.length === 0 && (
            <li style={{padding:'12px 16px',fontSize:13,color:'var(--ink-3)'}}>ยังไม่มีรายการ</li>
          )}
          {items.map(item => (
            <li key={item.id} style={{display:'flex',alignItems:'center',gap:6,padding:'8px 12px',borderTop:'1px solid var(--border)'}}>
              {editId === item.id ? (
                <>
                  <input className="input" style={{flex:1,padding:'5px 9px',fontSize:13}}
                    value={editVal}
                    onChange={e=>setEditVal(e.target.value)}
                    onKeyDown={e=>{ if(e.key==='Enter') saveEdit(item.id); if(e.key==='Escape') setEditId(null); }}
                    autoFocus/>
                  <button className="btn btn-primary btn-sm" disabled={busy===item.id} onClick={()=>saveEdit(item.id)}>
                    <Icon name="check" style={{width:13,height:13}}/> บันทึก
                  </button>
                  <button className="btn btn-ghost btn-sm" onClick={()=>setEditId(null)}>ยกเลิก</button>
                </>
              ) : (
                <>
                  <span style={{flex:1,fontSize:13}}>{item.name}</span>
                  <button className="icon-btn" title="แก้ไข" onClick={()=>{ setEditId(item.id); setEditVal(item.name); }}>
                    <Icon name="edit" style={{width:14,height:14}}/>
                  </button>
                  <button className="icon-btn" title="ลบ" style={{color:'var(--danger)'}}
                    onClick={()=>remove(item.id, item.name)}>
                    <Icon name="x" style={{width:14,height:14}}/>
                  </button>
                </>
              )}
            </li>
          ))}
        </ul>
      )}

      {/* แถวเพิ่มรายการใหม่ */}
      <div style={{display:'flex',gap:8,padding:'10px 12px',borderTop:'1px solid var(--border)',background:'var(--surface-2)'}}>
        <input className="input" style={{flex:1,fontSize:13}}
          placeholder="พิมพ์รายการใหม่ แล้วกด Enter หรือคลิก เพิ่ม"
          value={newName}
          onChange={e=>setNewName(e.target.value)}
          onKeyDown={e=>{ if(e.key==='Enter') addItem(); }}/>
        <button className="btn btn-primary btn-sm" disabled={adding || !newName.trim()} onClick={addItem}>
          <Icon name="filePlus" style={{width:13,height:13}}/> เพิ่ม
        </button>
      </div>
    </div>
  );
}

/* ── modal นำเข้า ZIP ───────────────────────────────────── */
function LookupImportModal({ onDone, onClose }) {
  const fileRef = React.useRef(null);
  const [file,    setFile]    = React.useState(null);
  const [busy,    setBusy]    = React.useState(false);
  const [result,  setResult]  = React.useState(null);
  const [err,     setErr]     = React.useState('');

  const submit = async e => {
    e.preventDefault();
    if (!file) return;
    setBusy(true); setErr(''); setResult(null);
    try {
      const fd = new FormData();
      fd.append('file', file);
      const res = await fetch('api/lookup.php?action=import', { method:'POST', credentials:'same-origin', body:fd });
      const j   = await res.json();
      if (!res.ok) throw new Error(j.error || 'นำเข้าไม่สำเร็จ');
      setResult(j);
      onDone();
    } catch(e) { setErr(e.message); }
    setBusy(false);
  };

  const OVERLAY = {position:'fixed',inset:0,background:'rgba(20,10,12,.55)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:200,padding:24};
  const BOX     = {background:'var(--surface)',borderRadius:12,boxShadow:'0 8px 40px rgba(0,0,0,.35)',width:'100%',maxWidth:420,display:'flex',flexDirection:'column',maxHeight:'90vh',overflow:'hidden'};

  return (
    <div style={OVERLAY} onClick={onClose}>
      <div style={BOX} onClick={e=>e.stopPropagation()}>
        <div style={{padding:'18px 22px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between',flexShrink:0}}>
          <h3 style={{margin:0,fontSize:16}}>นำเข้ารายการอ้างอิง</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <div style={{padding:'18px 22px',display:'flex',flexDirection:'column',gap:14,overflowY:'auto',flex:1}}>
          {err    && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}
          {result && (
            <div className="notice notice-ok"><Icon name="checkCircle"/>
              <div>นำเข้าสำเร็จ <b>{result.imported}</b> รายการ
                {result.skipped > 0 && <span className="muted"> (ข้าม {result.skipped})</span>}
                {result.errors?.length > 0 && <div className="muted sm" style={{marginTop:4}}>{result.errors.join(', ')}</div>}
              </div>
            </div>
          )}
          {!result && (
            <form id="lookup-import-form" onSubmit={submit} style={{display:'flex',flexDirection:'column',gap:14}}>
              <div className="notice" style={{background:'var(--surface-2)',border:'1px solid var(--border)',borderRadius:8,padding:'8px 12px',fontSize:13}}>
                <Icon name="info" style={{width:14,height:14,flexShrink:0}}/>
                <span className="muted">รองรับเฉพาะไฟล์ ZIP ที่ส่งออกจากระบบนี้เท่านั้น รายการที่มีชื่อซ้ำจะถูกอัปเดตค่า sort_order และสถานะ</span>
              </div>
              <div className="field">
                <label>เลือกไฟล์ ZIP <span className="req">*</span></label>
                <input ref={fileRef} type="file" accept=".zip,application/zip" className="input"
                  onChange={e=>setFile(e.target.files?.[0]||null)} required/>
              </div>
            </form>
          )}
        </div>
        <div className="modal-f">
          {result
            ? <button type="button" className="btn btn-primary" onClick={onClose}>ปิด</button>
            : <>
                <button type="button" className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
                <button type="submit" form="lookup-import-form" className="btn btn-primary" disabled={busy||!file}>
                  {busy ? <LoadingSpinner/> : 'นำเข้า'}
                </button>
              </>}
        </div>
      </div>
    </div>
  );
}

/* ── หน้าหลัก ────────────────────────────────────────────── */
function LookupManagePage() {
  const [exporting,    setExporting]    = React.useState(false);
  const [showImport,   setShowImport]   = React.useState(false);
  const [reloadKey,    setReloadKey]    = React.useState(0);

  const doExport = async () => {
    setExporting(true);
    try {
      const res = await fetch('api/lookup.php?action=export', { credentials: 'same-origin' });
      if (!res.ok) { const j = await res.json().catch(()=>({})); throw new Error(j.error || 'ส่งออกไม่สำเร็จ'); }
      const blob = await res.blob();
      const cd   = res.headers.get('Content-Disposition') || '';
      const m    = cd.match(/filename="([^"]+)"/);
      const name = m ? m[1] : 'lookups.zip';
      const a    = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = name;
      a.click();
      URL.revokeObjectURL(a.href);
    } catch(e) { alert(e.message); }
    setExporting(false);
  };

  return (
    <div className="fade-in">
      <PageHead title="จัดการรายการอ้างอิง" sub="ชื่อกลุ่มงาน และ ชื่อตำแหน่ง ที่ใช้เป็นตัวเลือก dropdown ในฟอร์มต่าง ๆ">
        <button className="btn btn-outline" onClick={doExport} disabled={exporting}>
          <Icon name="download" style={{width:15,height:15}}/>
          {exporting ? 'กำลังส่งออก…' : 'ส่งออก ZIP'}
        </button>
        <button className="btn btn-outline" onClick={() => setShowImport(true)}>
          <Icon name="upload" style={{width:15,height:15}}/> นำเข้า ZIP
        </button>
      </PageHead>
      <div className="notice notice-info" style={{marginBottom:18}}>
        <Icon name="info"/>
        <div>รายการที่เพิ่มที่นี่จะปรากฏเป็นตัวเลือกในฟอร์มเพิ่ม/แก้ไข <b>ผู้ใช้งาน</b> และ <b>บุคลากร</b> ทันที</div>
      </div>
      <div style={{display:'flex',gap:20,flexWrap:'wrap',alignItems:'flex-start'}}>
        {LOOKUP_CATS.map(c => <LookupSection key={c.cat + reloadKey} {...c}/>)}
      </div>
      {showImport && (
        <LookupImportModal
          onDone={() => setReloadKey(k => k + 1)}
          onClose={() => setShowImport(false)}/>
      )}
    </div>
  );
}

Object.assign(window, { LookupManagePage });
