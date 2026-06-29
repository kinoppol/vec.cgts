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

Object.assign(window, { RoleLabelsPage, SystemSettingsPage });
