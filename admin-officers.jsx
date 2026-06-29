/* ============================================================
   admin-officers.jsx — หน้าจัดการข้อมูลนิติกร (Admin)
   ============================================================ */

const EMPTY_OFFICER = {
  id: '', name: '', job_title: '', duty: '', group_name: '', init: '', active: 1,
};

/* สร้าง ID ถัดไปจากรายการที่มีอยู่ (o1, o2, … → oN+1) */
function nextOfficer(officers) {
  const nums = officers
    .map(o => parseInt((o.id || '').replace(/^o/, ''), 10))
    .filter(n => !isNaN(n));
  const nextNum = nums.length > 0 ? Math.max(...nums) + 1 : 1;
  return { ...EMPTY_OFFICER, id: `o${nextNum}`, _new: true };
}

/* ── Modal เพิ่ม / แก้ไข บุคลากร ─────────────────────────── */
function OfficerModal({ officer, lookupTitles, onSave, onClose }) {
  const isNew = !officer?.id || officer._new;
  const [form, setForm] = React.useState({ ...EMPTY_OFFICER, ...(officer || {}) });
  const [busy, setBusy] = React.useState(false);
  const [err, setErr] = React.useState('');

  const set = (k, v) => setForm(s => ({ ...s, [k]: v }));

  async function save() {
    if (!form.id.trim())   { setErr('ไม่สามารถระบุรหัสบุคลากรได้'); return; }
    if (!form.name.trim()) { setErr('กรุณาระบุชื่อ-นามสกุล'); return; }
    setBusy(true); setErr('');
    try {
      const saved = isNew
        ? await api.createOfficer(form)
        : await api.updateOfficer(form.id, form);
      onSave(saved);
    } catch(e) {
      setErr(e.message || 'เกิดข้อผิดพลาด');
    } finally { setBusy(false); }
  }

  return (
    <div className="overlay" onClick={onClose}>
      <div className="modal" style={{maxWidth:520}} onClick={e=>e.stopPropagation()}>
        <div className="modal-h">
          <div className="vcenter">
            <Icon name="users" style={{width:20,height:20,color:'var(--maroon)'}}/>
            <h3 style={{fontSize:17}}>{isNew ? 'เพิ่มบุคลากร' : 'แก้ไขข้อมูลบุคลากร'}</h3>
          </div>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>

        <div className="modal-b" style={{display:'flex',flexDirection:'column',gap:14}}>
          {err && <div className="notice notice-warn"><Icon name="alert"/><span>{err}</span></div>}

          <div className="grid" style={{gridTemplateColumns:'1fr 1fr',gap:12}}>
            <label className="lbl">
              รหัสบุคลากร
              <input className="input" value={form.id} disabled
                style={{background:'var(--surface-2)',cursor:'default',color:'var(--ink-3)'}}/>
              {isNew && <span className="tiny muted">สร้างอัตโนมัติ</span>}
            </label>
            <label className="lbl">
              ตัวย่อ (init)
              <input className="input" value={form.init} placeholder="เช่น กก"
                onChange={e=>set('init', e.target.value)}/>
            </label>
          </div>

          <label className="lbl">
            ชื่อ-นามสกุล <span style={{color:'var(--red)'}}>*</span>
            <input className="input" value={form.name} placeholder="นางสาว..."
              onChange={e=>set('name', e.target.value)}/>
          </label>

          <label className="lbl">
            ตำแหน่ง (job_title)
            {lookupTitles && lookupTitles.length > 0 ? (
              <LookupSelect value={form.job_title||''} items={lookupTitles}
                placeholder="— เลือกตำแหน่ง —"
                onChange={v=>set('job_title', v)}/>
            ) : (
              <input className="input" value={form.job_title} placeholder="นิติกรปฏิบัติการ"
                onChange={e=>set('job_title', e.target.value)}/>
            )}
          </label>

          <label className="lbl">
            หน้าที่ / ตำแหน่งในหน้าที่ (duty)
            <input className="input" value={form.duty} placeholder="ผู้อำนวยการกลุ่มงาน..."
              onChange={e=>set('duty', e.target.value)}/>
            <span className="tiny muted">กรณีมีตำแหน่งบริหารเพิ่มเติม</span>
          </label>

          {form.group_name && (
            <label className="lbl">
              กลุ่มงาน (สายงาน)
              <div className="input" style={{background:'var(--surface-2)',color:'var(--ink-3)',cursor:'default'}}>
                {form.group_name}
              </div>
              <span className="tiny faint">กำหนดอัตโนมัติจากกลุ่มที่สังกัด — แก้ไขได้ที่ จัดการกลุ่ม</span>
            </label>
          )}

          <label className="lbl" style={{flexDirection:'row',alignItems:'center',gap:10,cursor:'pointer'}}>
            <input type="checkbox" checked={!!form.active}
              onChange={e=>set('active', e.target.checked ? 1 : 0)}/>
            ยังปฏิบัติหน้าที่ (active)
          </label>
        </div>

        <div className="modal-f">
          <button className="btn btn-outline" onClick={onClose}>ยกเลิก</button>
          <button className="btn btn-primary" disabled={busy} onClick={save}>
            <Icon name="check" style={{width:16,height:16}}/> {busy ? 'กำลังบันทึก…' : 'บันทึก'}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ── Modal นำเข้าบุคลากรจาก ZIP ─────────────────────────── */
function ImportModal({ onDone, onClose }) {
  const [file, setFile]     = React.useState(null);
  const [busy, setBusy]     = React.useState(false);
  const [result, setResult] = React.useState(null);
  const [err, setErr]       = React.useState('');
  const fileRef             = React.useRef(null);

  async function doImport() {
    if (!file) { setErr('กรุณาเลือกไฟล์ ZIP'); return; }
    setBusy(true); setErr('');
    try {
      const fd = new FormData();
      fd.append('file', file);
      const res = await fetch('api/officers_transfer.php', { method:'POST', body: fd });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'นำเข้าล้มเหลว');
      setResult(data);
      onDone();
    } catch(e) { setErr(e.message); }
    finally { setBusy(false); }
  }

  return (
    <div className="overlay" onClick={onClose}>
      <div className="modal" style={{maxWidth:480}} onClick={e=>e.stopPropagation()}>
        <div className="modal-h">
          <div className="vcenter">
            <Icon name="upload" style={{width:20,height:20,color:'var(--maroon)'}}/>
            <h3 style={{fontSize:17}}>นำเข้าข้อมูลบุคลากร</h3>
          </div>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <div className="modal-b" style={{display:'flex',flexDirection:'column',gap:14}}>
          {!result ? (
            <>
              <div className="notice" style={{background:'var(--surface-2)',border:'1px solid var(--border)',borderRadius:8,padding:'10px 12px',fontSize:13,gap:8}}>
                <Icon name="alert" style={{width:14,height:14,color:'var(--warn)',flexShrink:0}}/>
                <span>ใช้ไฟล์ ZIP ที่ส่งออกจากระบบนี้เท่านั้น ข้อมูลที่มีอยู่แล้วจะถูกอัปเดต (UPSERT)</span>
              </div>
              {err && <div className="notice notice-warn"><Icon name="alert"/><span>{err}</span></div>}
              <label className="lbl">
                ไฟล์ ZIP <span style={{color:'var(--red)'}}>*</span>
                <div className="vcenter" style={{gap:8}}>
                  <input ref={fileRef} type="file" accept=".zip,application/zip" style={{display:'none'}}
                    onChange={e=>setFile(e.target.files?.[0]||null)}/>
                  <button className="btn btn-outline" type="button" onClick={()=>fileRef.current?.click()}>
                    <Icon name="paperclip" style={{width:15,height:15}}/> เลือกไฟล์
                  </button>
                  <span className="sm muted">{file ? file.name : 'ยังไม่ได้เลือก'}</span>
                </div>
              </label>
            </>
          ) : (
            <div style={{display:'flex',flexDirection:'column',gap:10}}>
              <div className="notice notice-ok">
                <Icon name="checkCircle" style={{width:18,height:18}}/>
                <div>
                  <div style={{fontWeight:600}}>นำเข้าสำเร็จ</div>
                  <div className="sm">บุคลากร {result.imported} รายการ · ภาพ {result.avatars_imported} รูป</div>
                </div>
              </div>
              {result.errors?.length > 0 && (
                <div className="notice notice-warn" style={{flexDirection:'column',alignItems:'flex-start',gap:4}}>
                  <div className="vcenter" style={{gap:6}}><Icon name="alert" style={{width:14,height:14}}/><b>พบข้อผิดพลาด {result.errors.length} รายการ</b></div>
                  <ul style={{margin:'4px 0 0 18px',padding:0,fontSize:12}}>
                    {result.errors.map((e,i)=><li key={i}>{e}</li>)}
                  </ul>
                </div>
              )}
            </div>
          )}
        </div>
        <div className="modal-f">
          {!result ? (
            <>
              <button className="btn btn-outline" onClick={onClose}>ยกเลิก</button>
              <button className="btn btn-primary" disabled={busy||!file} onClick={doImport}>
                <Icon name="upload" style={{width:15,height:15}}/> {busy ? 'กำลังนำเข้า…' : 'นำเข้า'}
              </button>
            </>
          ) : (
            <button className="btn btn-primary" onClick={onClose}>ปิด</button>
          )}
        </div>
      </div>
    </div>
  );
}

/* ── หน้าหลัก จัดการบุคลากร ──────────────────────────────── */
function OfficerManagePage() {
  const [officers, setOfficers]         = React.useState([]);
  const [loading, setLoading]           = React.useState(true);
  const [modal, setModal]               = React.useState(null);
  const [showImport, setShowImport]     = React.useState(false);
  const [exporting, setExporting]       = React.useState(false);
  const [q, setQ]                       = React.useState('');
  const [grpFilter, setGrpFilter]       = React.useState('');
  const [showInactive, setShowInactive] = React.useState(false);
  const [busy, setBusy]                 = React.useState('');
  const [lookupTitles, setLookupTitles] = React.useState([]);

  React.useEffect(() => {
    Promise.all([
      api.listAllOfficers(),
      api.getLookups('job_title'),
    ]).then(([data, t]) => {
      setOfficers(data); setLookupTitles(t); setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  function handleSaved(saved) {
    setOfficers(prev => {
      const idx = prev.findIndex(o => o.id === saved.id);
      return idx >= 0 ? prev.map(o => o.id === saved.id ? saved : o) : [...prev, saved];
    });
    setModal(null);
  }

  async function doExport() {
    setExporting(true);
    try {
      const res = await fetch('api/officers_transfer.php');
      if (!res.ok) { const d = await res.json(); throw new Error(d.error||'ส่งออกล้มเหลว'); }
      const blob = await res.blob();
      const cd   = res.headers.get('Content-Disposition') || '';
      const name = (cd.match(/filename="([^"]+)"/) || [])[1] || 'officers.zip';
      const url  = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href = url; a.download = name;
      document.body.appendChild(a); a.click();
      document.body.removeChild(a); URL.revokeObjectURL(url);
    } catch(e) { alert(e.message); }
    finally { setExporting(false); }
  }

  async function toggleActive(o) {
    const newActive = o.active ? 0 : 1;
    setBusy(o.id);
    try {
      const saved = await api.updateOfficer(o.id, { active: newActive });
      setOfficers(prev => prev.map(x => x.id === o.id ? saved : x));
    } catch(e) { alert(e.message); }
    finally { setBusy(''); }
  }

  const filtered = officers.filter(o => {
    if (!showInactive && !o.active) return false;
    if (grpFilter && o.group_name !== grpFilter) return false;
    if (q) {
      const s = q.toLowerCase();
      return o.name.includes(q) || (o.job_title||'').includes(q) || (o.duty||'').includes(q) || (o.init||'').toLowerCase().includes(s);
    }
    return true;
  });

  const groups = [...new Set(officers.map(o=>o.group_name).filter(Boolean))];

  return (
    <div className="fade-in">
      <PageHead title="จัดการข้อมูลบุคลากร" sub="เพิ่ม แก้ไข และบริหารรายชื่อบุคลากร/ผู้รับผิดชอบสำนวน">
        <div className="vcenter" style={{gap:8}}>
          <button className="btn btn-outline" disabled={exporting} onClick={doExport}>
            <Icon name="download" style={{width:15,height:15}}/> {exporting ? 'กำลังส่งออก…' : 'ส่งออก ZIP'}
          </button>
          <button className="btn btn-outline" onClick={()=>setShowImport(true)}>
            <Icon name="upload" style={{width:15,height:15}}/> นำเข้า ZIP
          </button>
          <button className="btn btn-primary" onClick={()=>setModal(nextOfficer(officers))}>
            <Icon name="filePlus" style={{width:16,height:16}}/> เพิ่มบุคลากร
          </button>
        </div>
      </PageHead>

      {/* filter bar */}
      <div className="card" style={{marginBottom:16,padding:'12px 16px'}}>
        <div className="vcenter" style={{gap:10,flexWrap:'wrap'}}>
          <div style={{position:'relative',flex:'1 1 200px'}}>
            <Icon name="search" style={{position:'absolute',left:10,top:'50%',transform:'translateY(-50%)',width:16,height:16,color:'var(--ink-3)'}}/>
            <input className="input" style={{paddingLeft:34}} placeholder="ค้นหาชื่อ ตำแหน่ง ตัวย่อ…"
              value={q} onChange={e=>setQ(e.target.value)}/>
          </div>
          <select className="input" style={{flex:'0 0 220px'}} value={grpFilter} onChange={e=>setGrpFilter(e.target.value)}>
            <option value="">-- ทุกกลุ่มงาน --</option>
            {groups.map(g=><option key={g} value={g}>{g}</option>)}
          </select>
          <label className="vcenter" style={{gap:6,cursor:'pointer',fontSize:13}}>
            <input type="checkbox" checked={showInactive} onChange={e=>setShowInactive(e.target.checked)}/>
            แสดงผู้ไม่ active
          </label>
        </div>
      </div>

      {loading
        ? <div className="muted" style={{padding:32,textAlign:'center'}}>กำลังโหลด…</div>
        : (
        <div className="card">
          <div className="table-wrap">
            <table className="tbl">
              <thead>
                <tr>
                  <th style={{width:60}}>รหัส</th>
                  <th>ชื่อ-นามสกุล</th>
                  <th>ตำแหน่ง / หน้าที่</th>
                  <th>กลุ่มงาน</th>
                  <th style={{width:50}}>ย่อ</th>
                  <th style={{width:70}}>ภาระ</th>
                  <th style={{width:80}}>สถานะ</th>
                  <th style={{width:80}}></th>
                </tr>
              </thead>
              <tbody>
                {filtered.length === 0
                  ? <tr><td colSpan={8} className="muted" style={{textAlign:'center',padding:24}}>ไม่พบข้อมูล</td></tr>
                  : filtered.map(o=>(
                  <tr key={o.id} style={!o.active ? {opacity:.5} : {}}>
                    <td><div className="code">{o.id}</div></td>
                    <td>
                      <div style={{fontWeight:500}}>{o.name}</div>
                    </td>
                    <td>
                      {o.duty
                        ? <>
                            <div style={{fontWeight:500,fontSize:13}}>{o.duty}</div>
                            <div className="tiny muted">{o.job_title}</div>
                          </>
                        : <div className="sm">{o.job_title}</div>
                      }
                    </td>
                    <td className="sm muted">{o.group_name}</td>
                    <td className="sm">{o.init}</td>
                    <td className="sm muted">{o.load} เรื่อง</td>
                    <td>
                      {o.active
                        ? <span className="badge badge-ok">ปฏิบัติงาน</span>
                        : <span className="badge badge-warn">ไม่ active</span>
                      }
                    </td>
                    <td>
                      <div className="vcenter" style={{gap:6}}>
                        <button className="icon-btn" title="แก้ไข" onClick={()=>setModal(o)}>
                          <Icon name="edit" style={{width:15,height:15}}/>
                        </button>
                        <button className="icon-btn" title={o.active ? 'ปิดใช้งาน' : 'เปิดใช้งาน'}
                          disabled={busy===o.id} onClick={()=>toggleActive(o)}>
                          <Icon name={o.active ? 'eyeOff' : 'eye'} style={{width:15,height:15}}/>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="card-pad tiny muted" style={{borderTop:'1px solid var(--border)'}}>
            แสดง {filtered.length} / {officers.length} รายการ
            {!showInactive && officers.some(o=>!o.active) && (
              <> · <button style={{background:'none',border:'none',color:'var(--maroon)',cursor:'pointer',padding:0,fontSize:'inherit'}} onClick={()=>setShowInactive(true)}>
                มีผู้ไม่ active {officers.filter(o=>!o.active).length} คน — คลิกเพื่อดู
              </button></>
            )}
          </div>
        </div>
      )}

      {modal && (
        <OfficerModal
          officer={modal}
          lookupTitles={lookupTitles}
          onSave={handleSaved}
          onClose={()=>setModal(null)}
        />
      )}

      {showImport && (
        <ImportModal
          onDone={() => {
            // โหลดรายการบุคลากรใหม่หลังนำเข้าสำเร็จ
            api.listAllOfficers().then(setOfficers).catch(()=>{});
          }}
          onClose={()=>setShowImport(false)}
        />
      )}
    </div>
  );
}

Object.assign(window, { OfficerManagePage });
