/* ============================================================
   admin-roles.jsx — จัดการชื่อบทบาทผู้ใช้งาน
   เข้าถึงได้โดย: admin เท่านั้น
   ============================================================ */

function RoleLabelsPage({ roleLabels, onUpdate }) {
  const [drafts, setDrafts] = useState({});
  const [saving, setSaving] = useState({});
  const [flash,  setFlash]  = useState('');
  const [errMsg, setErrMsg] = useState('');

  useEffect(() => {
    const d = {};
    ROLE_ORDER.forEach(r => { d[r] = roleLabel(r, roleLabels); });
    setDrafts(d);
  }, [roleLabels]);

  function isDirty(role) {
    return (drafts[role] || '') !== roleLabel(role, roleLabels);
  }

  async function save(role) {
    const label = (drafts[role] || '').trim();
    if (!label) { setErrMsg('ชื่อบทบาทต้องไม่ว่างเปล่า'); return; }
    setSaving(s => ({ ...s, [role]: true }));
    setErrMsg('');
    try {
      await api.saveRoleLabel({ role, label });
      onUpdate({ ...roleLabels, [role]: label });
      showFlash('บันทึกชื่อบทบาท "' + label + '" เรียบร้อย');
    } catch(e) { setErrMsg(e.message); }
    setSaving(s => ({ ...s, [role]: false }));
  }

  function showFlash(msg) {
    setFlash(msg);
    setTimeout(() => setFlash(''), 3500);
  }

  const ROLE_DESC = {
    officer:          'เจ้าหน้าที่ปฏิบัติงาน รับเรื่อง และดำเนินการสำนวน',
    dir_legal:        'ผู้อำนวยการกลุ่มนิติการ กำกับดูแลงานสอบสวน',
    dir_admin:        'ผู้อำนวยการสำนักอำนวยการ ติดตามภาพรวมสำนัก',
    secretary:        'เลขาธิการ สอศ.',
    deputy_secretary: 'รองเลขาธิการ สอศ.',
    admin:            'ผู้ดูแลระบบ มีสิทธิ์เต็มในทุกส่วนของระบบ',
  };

  return (
    <div className="fade-in">
      <PageHead title="จัดการชื่อบทบาท" sub="กำหนดชื่อที่แสดงสำหรับแต่ละบทบาทผู้ใช้งานในระบบ"/>

      {errMsg && (
        <div className="notice notice-err" style={{marginBottom:16}}>
          <Icon name="alert"/><div>{errMsg}</div>
        </div>
      )}
      {flash && (
        <div className="notice notice-ok" style={{marginBottom:16}}>
          <Icon name="checkCircle"/><div>{flash}</div>
        </div>
      )}

      <div className="notice notice-info" style={{marginBottom:20}}>
        <Icon name="info"/>
        <div>
          ชื่อบทบาทที่แก้ไขจะแสดงใน <b>ส่วนหัว, แถบด้านข้าง, ตารางผู้ใช้</b> และ <b>แบบฟอร์มต่าง ๆ</b> ทั่วทั้งระบบ
          ผู้ใช้ที่ล็อกอินอยู่จะเห็นชื่อใหม่ทันทีหลังบันทึก
        </div>
      </div>

      <div className="card">
        <div className="table-wrap">
          <table className="tbl">
            <thead>
              <tr>
                <th style={{width:220}}>ค่าในระบบ (role)</th>
                <th>ชื่อที่แสดง</th>
                <th style={{width:90,textAlign:'center'}}></th>
              </tr>
            </thead>
            <tbody>
              {ROLE_ORDER.map(role => {
                const dirty = isDirty(role);
                const busy  = !!saving[role];
                return (
                  <tr key={role}>
                    <td>
                      <code style={{
                        background:'var(--surface-2)',padding:'3px 9px',
                        borderRadius:4,fontSize:12,display:'inline-block',
                        border:'1px solid var(--line)',fontFamily:'monospace',
                      }}>{role}</code>
                      <div className="faint tiny" style={{marginTop:4}}>{ROLE_DESC[role]}</div>
                    </td>
                    <td>
                      <input type="text" className="input"
                        style={{padding:'6px 10px',fontSize:14,width:'100%',maxWidth:360}}
                        value={drafts[role] || ''}
                        onChange={e => setDrafts(d => ({...d, [role]: e.target.value}))}
                        onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); save(role); } }}/>
                    </td>
                    <td style={{textAlign:'center'}}>
                      <button className="btn btn-sm"
                        style={{
                          background: dirty && !busy ? 'var(--maroon)' : 'var(--line)',
                          color:      dirty && !busy ? '#fff' : 'var(--ink-3)',
                          transition: 'all .18s', minWidth:68,
                        }}
                        disabled={!dirty || busy}
                        onClick={() => save(role)}>
                        {busy ? '…' : 'บันทึก'}
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

/* ---------------- SystemSettingsPage — ตั้งค่าระบบ (admin) ---------------- */
function SystemSettingsPage() {
  const [settings, setSettings] = React.useState(null);
  const [prefix,   setPrefix]   = React.useState('');
  const [nextSeq,  setNextSeq]  = React.useState('');
  const [preview,  setPreview]  = React.useState('');
  const [saving,   setSaving]   = React.useState(false);
  const [msg,      setMsg]      = React.useState('');

  const loadPreview = () => api.getNextCaseId().then(r=>setPreview(r.next_case_id||'')).catch(()=>{});

  React.useEffect(() => {
    api.getSettings().then(s => {
      setSettings(s);
      setPrefix(s.case_id_prefix || 'CMP');
      setNextSeq(s.case_id_next_seq || '');
    }).catch(() => {});
    loadPreview();
  }, []);

  const save = async () => {
    setSaving(true); setMsg('');
    try {
      const payload = { case_id_prefix: prefix.trim() || 'CMP' };
      if (nextSeq.trim()) payload.case_id_next_seq = nextSeq.trim();
      const s = await api.saveSettings(payload);
      setSettings(s);
      setPrefix(s.case_id_prefix || 'CMP');
      setNextSeq(s.case_id_next_seq || '');
      await loadPreview();
      setMsg('บันทึกแล้ว');
    } catch(e) { setMsg('เกิดข้อผิดพลาด: ' + e.message); }
    setSaving(false);
  };

  if (!settings) return <LoadingSpinner/>;

  return (
    <div className="fade-in">
      <PageHead title="ตั้งค่าระบบ" sub="การตั้งค่าทั่วไปสำหรับผู้ดูแลระบบ"/>
      <div className="card card-pad" style={{maxWidth:520}}>
        <h3 style={{fontSize:15,marginBottom:16}}>รหัสสำนวน / เลขรับ</h3>
        <div className="field">
          <label>Prefix รหัสสำนวน</label>
          <div className="vcenter" style={{gap:8}}>
            <input className="input" style={{width:120,fontFamily:'monospace',fontWeight:600,textTransform:'uppercase'}}
              value={prefix} maxLength={10}
              onChange={e=>setPrefix(e.target.value.toUpperCase().replace(/[^A-Z0-9ก-๙]/g,''))}
            />
          </div>
          <div className="faint tiny" style={{marginTop:4}}>ใช้ตัวอักษรภาษาอังกฤษหรือตัวเลข ไม่มีขีด (-) จะเพิ่มให้อัตโนมัติ</div>
        </div>
        <div className="field">
          <label>เลขลำดับถัดไป <span className="faint" style={{fontWeight:400}}>(ปล่อยว่าง = ต่อจากเลขล่าสุดอัตโนมัติ)</span></label>
          <div className="vcenter" style={{gap:8}}>
            <input className="input" style={{width:120,fontFamily:'monospace',fontWeight:600}} type="number" min="1"
              value={nextSeq} placeholder="อัตโนมัติ"
              onChange={e=>setNextSeq(e.target.value.replace(/\D/g,''))}
            />
            {preview && <span className="faint sm">รหัสถัดไป: <b style={{fontFamily:'monospace',color:'var(--maroon)'}}>{preview}</b></span>}
          </div>
          <div className="faint tiny" style={{marginTop:4}}>ใช้ครั้งเดียว — หลังออกเลขแล้วจะกลับเป็นอัตโนมัติ</div>
        </div>
        {msg && <div className={'notice '+(msg.startsWith('บันทึก')?'notice-ok':'notice-err')} style={{marginTop:8}}>{msg}</div>}
        <div style={{marginTop:16,display:'flex',justifyContent:'flex-end'}}>
          <button className="btn btn-primary" disabled={saving} onClick={save}>
            <Icon name="save" style={{width:15,height:15}}/> {saving?'กำลังบันทึก…':'บันทึก'}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ---------------- TestDataPage — จัดการข้อมูลทดสอบระบบ (admin) ---------------- */
function TestDataPage({ cases = [], onChanged }) {
  const [info, setInfo]   = React.useState(null);
  const [err, setErr]     = React.useState('');
  const [msg, setMsg]     = React.useState('');

  const [sel, setSel]         = React.useState({});   // {caseId: true}
  const [q, setQ]             = React.useState('');
  const [delPw, setDelPw]     = React.useState('');
  const [delBusy, setDelBusy] = React.useState(false);
  const [confirmDel, setConfirmDel] = React.useState(false);

  const [mainNext, setMainNext] = React.useState('');
  const [mainPw, setMainPw]     = React.useState('');
  const [mainBusy, setMainBusy] = React.useState(false);

  const [grpNext, setGrpNext] = React.useState({});
  const [grpPw, setGrpPw]     = React.useState('');
  const [grpBusy, setGrpBusy] = React.useState(0);

  const load = () => api.getTestDataInfo().then(setInfo).catch(e => setErr(e.message));
  React.useEffect(() => { load(); }, []);

  const flash = (m) => { setMsg(m); setErr(''); setTimeout(()=>setMsg(''), 4000); };

  const kw = q.trim().toLowerCase();
  const rows = cases.filter(c => !kw || c.id.toLowerCase().includes(kw) || (c.subject||'').toLowerCase().includes(kw));
  const idList = Object.keys(sel).filter(id => sel[id]);
  const allVisibleSelected = rows.length > 0 && rows.every(c => sel[c.id]);
  const toggle = (id) => setSel(s => ({ ...s, [id]: !s[id] }));
  const toggleAll = () => {
    if (allVisibleSelected) { const n={...sel}; rows.forEach(c=>delete n[c.id]); setSel(n); }
    else setSel(s => { const n={...s}; rows.forEach(c=>n[c.id]=true); return n; });
  };

  const doDelete = async () => {
    setDelBusy(true); setErr('');
    try {
      const r = await api.testDataAction({ action:'bulk_delete', ids: idList, password: delPw });
      flash(`ลบสำเร็จ ${r.deleted.length} เรื่อง` + (r.not_found.length ? ` · ไม่พบ ${r.not_found.length} เรื่อง (${r.not_found.join(', ')})` : ''));
      setSel({}); setDelPw(''); setConfirmDel(false);
      if (onChanged) onChanged(); load();
    } catch(e) { setErr(e.message); }
    setDelBusy(false);
  };

  const saveMainSeq = async () => {
    setMainBusy(true); setErr('');
    try {
      const r = await api.testDataAction({ action:'reset_main_seq', next_seq: parseInt(mainNext,10), password: mainPw });
      flash(`ตั้งเลขทะเบียนหลักถัดไป: ${r.next_id}`);
      setMainNext(''); setMainPw(''); load();
    } catch(e) { setErr(e.message); }
    setMainBusy(false);
  };

  const saveGroupSeq = async (gid) => {
    setGrpBusy(gid); setErr('');
    try {
      await api.testDataAction({ action:'reset_group_seq', group_id: gid, next_seq: parseInt(grpNext[gid],10), password: grpPw });
      flash('บันทึกเลขรับกลุ่มแล้ว');
      setGrpNext(s=>({ ...s, [gid]:'' })); load();
    } catch(e) { setErr(e.message); }
    setGrpBusy(0);
  };

  if (!info) return <div className="fade-in"><PageHead title="จัดการข้อมูลทดสอบ" sub="สำหรับผู้ดูแลระบบเท่านั้น"/>{err ? <div className="notice notice-danger"><Icon name="alert"/><div>{err}</div></div> : <LoadingSpinner/>}</div>;

  return (
    <div className="fade-in">
      <PageHead title="จัดการข้อมูลทดสอบ" sub="ลบเรื่องทดสอบ + รีเซ็ตเลขทะเบียน — สำหรับผู้ดูแลระบบเท่านั้น"/>

      {msg && <div className="notice notice-ok" style={{marginBottom:14}}><Icon name="checkCircle"/><div>{msg}</div></div>}
      {err && <div className="notice notice-danger" style={{marginBottom:14}}><Icon name="alert"/><div>{err}</div></div>}

      <div className="card card-pad" style={{marginBottom:18,borderLeft:'3px solid var(--danger)'}}>
        <h3 style={{fontSize:15,marginBottom:6,color:'var(--danger)'}}><Icon name="alert" style={{width:16,height:16}}/> ลบเรื่องทดสอบ (หลายเรื่องพร้อมกัน)</h3>
        <div className="notice notice-danger" style={{marginBottom:12}}><Icon name="alert"/><div>การดำเนินการนี้<b>มีความเสี่ยงสูงและไม่สามารถย้อนกลับได้</b> — จะลบสำนวน ไฟล์ ไทม์ไลน์ การเกษียน การแจ้งเตือน และเลขรับกลุ่มที่เกี่ยวข้องทั้งหมด</div></div>
        <input className="input" value={q} onChange={e=>setQ(e.target.value)} placeholder="ค้นหา รหัส / ชื่อเรื่อง…" style={{maxWidth:340,marginBottom:10}}/>
        <div className="table-wrap" style={{maxHeight:360,overflowY:'auto',border:'1px solid var(--line)',borderRadius:8}}>
          <table className="tbl">
            <thead><tr>
              <th style={{width:40,textAlign:'center'}}><input type="checkbox" checked={allVisibleSelected} onChange={toggleAll}/></th>
              <th>เลขทะเบียน</th><th>ชื่อเรื่อง</th><th>สถานะล่าสุด</th>
            </tr></thead>
            <tbody>
              {rows.map(c => (
                <tr key={c.id} onClick={()=>toggle(c.id)} style={{cursor:'pointer',background: sel[c.id]?'var(--danger-bg,#fef2f2)':undefined}}>
                  <td style={{textAlign:'center'}} onClick={e=>e.stopPropagation()}><input type="checkbox" checked={!!sel[c.id]} onChange={()=>toggle(c.id)}/></td>
                  <td><div className="code sm">{c.id}</div></td>
                  <td style={{maxWidth:360}}><div style={{whiteSpace:'nowrap',overflow:'hidden',textOverflow:'ellipsis'}}>{c.subject}</div></td>
                  <td><StatusBadge s={c.status}/></td>
                </tr>
              ))}
              {rows.length===0 && <tr><td colSpan={4} className="faint sm" style={{textAlign:'center',padding:'14px'}}>ไม่พบเรื่อง</td></tr>}
            </tbody>
          </table>
        </div>
        <div className="faint tiny" style={{marginTop:6}}>เลือก {idList.length} จาก {cases.length} เรื่อง</div>
        {!confirmDel
          ? <button className="btn btn-outline" style={{marginTop:12,color:'var(--danger)',borderColor:'var(--danger)'}} disabled={idList.length===0} onClick={()=>setConfirmDel(true)}>
              <Icon name="x" style={{width:15,height:15}}/> ดำเนินการลบ {idList.length||''} เรื่อง
            </button>
          : <div style={{marginTop:12,padding:'12px 14px',background:'var(--danger-bg,#fef2f2)',borderRadius:8}}>
              <div style={{fontWeight:600,marginBottom:8,color:'var(--danger)'}}>ยืนยันการลบ {idList.length} เรื่อง — กรอกรหัสผ่าน admin</div>
              <input className="input" type="password" value={delPw} onChange={e=>setDelPw(e.target.value)} placeholder="รหัสผ่าน admin" style={{maxWidth:280}}/>
              <div className="vcenter" style={{gap:8,marginTop:10}}>
                <button className="btn btn-ghost" onClick={()=>{setConfirmDel(false);setDelPw('');}} disabled={delBusy}>ยกเลิก</button>
                <button className="btn btn-primary" style={{background:'var(--danger)'}} disabled={delBusy || !delPw} onClick={doDelete}>{delBusy?'กำลังลบ…':'ยืนยันลบถาวร'}</button>
              </div>
            </div>}
      </div>

      <div className="card card-pad" style={{marginBottom:18}}>
        <h3 style={{fontSize:15,marginBottom:10}}>เลขทะเบียนหนังสือหลัก (ระบบ)</h3>
        <div className="faint sm" style={{marginBottom:10}}>รูปแบบ {info.main.prefix}XXXX · เลขล่าสุดในระบบ: <b className="tnum">{String(info.main.max_seq).padStart(4,'0')}</b>{info.main.next_override ? ` · ตั้งเลขถัดไปไว้: ${String(info.main.next_override).padStart(4,'0')}` : ''}</div>
        <div className="vcenter" style={{gap:8,flexWrap:'wrap'}}>
          <input className="input" type="number" min="1" value={mainNext} onChange={e=>setMainNext(e.target.value)} placeholder="เลขถัดไป" style={{width:120}}/>
          <input className="input" type="password" value={mainPw} onChange={e=>setMainPw(e.target.value)} placeholder="รหัสผ่าน admin" style={{width:200}}/>
          <button className="btn btn-primary" disabled={mainBusy || !mainNext || !mainPw} onClick={saveMainSeq}>{mainBusy?'กำลังบันทึก…':'บันทึก'}</button>
        </div>
        <div className="faint tiny" style={{marginTop:6}}>ต้องมากกว่าเลขล่าสุดที่มีอยู่ ({String(info.main.max_seq).padStart(4,'0')}) เพื่อป้องกันเลขซ้ำ</div>
      </div>

      <div className="card card-pad">
        <h3 style={{fontSize:15,marginBottom:10}}>เลขรับหนังสือของกลุ่ม (ปี {info.year})</h3>
        {info.groups.length === 0
          ? <div className="faint sm">ยังไม่มีกลุ่ม</div>
          : <div style={{display:'flex',flexDirection:'column',gap:10}}>
              {info.groups.map(g => (
                <div key={g.id} className="between" style={{gap:12,flexWrap:'wrap',padding:'10px 0',borderBottom:'1px solid var(--line)'}}>
                  <div style={{minWidth:200}}>
                    <div style={{fontWeight:600}}>{g.name}</div>
                    <div className="faint tiny">{g.recv_prefix || '(ยังไม่ตั้ง prefix)'} · เลขล่าสุด {String(g.max_seq).padStart(5,'0')}{g.next_override ? ` · ตั้งถัดไป ${String(g.next_override).padStart(5,'0')}` : ''}</div>
                  </div>
                  <div className="vcenter" style={{gap:8}}>
                    <input className="input" type="number" min="1" value={grpNext[g.id]||''} onChange={e=>setGrpNext(s=>({...s,[g.id]:e.target.value}))} placeholder="เลขถัดไป" style={{width:110}}/>
                    <button className="btn btn-outline btn-sm" disabled={grpBusy===g.id || !grpNext[g.id] || !grpPw} onClick={()=>saveGroupSeq(g.id)}>{grpBusy===g.id?'…':'บันทึก'}</button>
                  </div>
                </div>
              ))}
              <div className="vcenter" style={{gap:8,marginTop:6}}>
                <Icon name="lock" style={{width:15,height:15,color:'var(--ink-3)'}}/>
                <input className="input" type="password" value={grpPw} onChange={e=>setGrpPw(e.target.value)} placeholder="รหัสผ่าน admin (ใช้กับการบันทึกเลขกลุ่ม)" style={{maxWidth:320}}/>
              </div>
            </div>}
      </div>
    </div>
  );
}

Object.assign(window, { RoleLabelsPage, SystemSettingsPage, TestDataPage });
