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

Object.assign(window, { RoleLabelsPage });
