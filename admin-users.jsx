/* ============================================================
   admin-users.jsx — จัดการผู้ใช้งานระบบ
   ============================================================ */

const ROLE_OPTS = [
  { v:'officer',   l:'เจ้าหน้าที่นิติการ / ธุรการ' },
  { v:'dir_legal', l:'ผอ.กลุ่มนิติการ' },
  { v:'dir_admin', l:'ผอ.สำนักอำนวยการ' },
  { v:'admin',     l:'ผู้ดูแลระบบ' },
];
const ROLE_BADGE = {
  officer:   'badge',
  dir_legal: 'badge-info',
  dir_admin: 'badge-warn',
  admin:     'badge-danger',
};

/* ---------- modal เพิ่ม / แก้ไข ---------- */
function UserModal({ user, officers, onSave, onClose }) {
  const isNew = !user?.id;
  const [form, setForm] = useState(user ? { ...user, password:'' } : {
    username:'', display_name:'', role:'officer',
    init:'', officer_id:'', can_manage_users:false, active:true, password:''
  });
  const [saving, setSaving] = useState(false);
  const [err, setErr]       = useState('');

  const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

  const submit = async e => {
    e.preventDefault();
    setSaving(true); setErr('');
    try {
      let saved;
      if (isNew) {
        saved = await apiFetch('/api/users.php', { method:'POST', body: JSON.stringify(form) });
      } else {
        const patch = { display_name:form.display_name, role:form.role, init:form.init,
                        officer_id:form.officer_id||null, active:form.active?1:0,
                        can_manage_users:form.can_manage_users?1:0 };
        saved = await apiFetch('/api/users.php?id='+user.id, { method:'PATCH', body: JSON.stringify(patch) });
      }
      onSave(saved, isNew);
    } catch(e) { setErr(e.message); }
    setSaving(false);
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-box" style={{maxWidth:520}} onClick={e=>e.stopPropagation()}>
        <div className="modal-head">
          <h3>{isNew ? 'เพิ่มผู้ใช้ใหม่' : 'แก้ไขผู้ใช้'}</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <form onSubmit={submit} style={{padding:'0 24px 24px',display:'flex',flexDirection:'column',gap:14}}>
          {err && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}

          <div className="form-grid" style={{gridTemplateColumns:'1fr 1fr',gap:14}}>
            <div className="field">
              <label>ชื่อผู้ใช้ <span className="req">*</span></label>
              <input className="input" value={form.username} onChange={e=>set('username',e.target.value)}
                disabled={!isNew} required placeholder="a-z, 0-9, _"/>
            </div>
            <div className="field">
              <label>ตัวย่อ</label>
              <input className="input" value={form.init||''} onChange={e=>set('init',e.target.value)} maxLength={5} placeholder="เช่น วว"/>
            </div>
          </div>

          <div className="field">
            <label>ชื่อแสดง <span className="req">*</span></label>
            <input className="input" value={form.display_name} onChange={e=>set('display_name',e.target.value)} required/>
          </div>

          {isNew && (
            <div className="field">
              <label>รหัสผ่าน <span className="req">*</span></label>
              <input className="input" type="password" value={form.password}
                onChange={e=>set('password',e.target.value)} required minLength={6} autoComplete="new-password"/>
              <span className="hint">อย่างน้อย 6 ตัวอักษร</span>
            </div>
          )}

          <div className="form-grid" style={{gridTemplateColumns:'1fr 1fr',gap:14}}>
            <div className="field">
              <label>บทบาท</label>
              <select className="input" value={form.role} onChange={e=>set('role',e.target.value)}>
                {ROLE_OPTS.map(o=><option key={o.v} value={o.v}>{o.l}</option>)}
              </select>
            </div>
            <div className="field">
              <label>เชื่อมกับนิติกร</label>
              <select className="input" value={form.officer_id||''} onChange={e=>set('officer_id',e.target.value)}>
                <option value="">— ไม่มี —</option>
                {(officers||[]).map(o=><option key={o.id} value={o.id}>{o.name}</option>)}
              </select>
            </div>
          </div>

          <div style={{display:'flex',gap:20,flexWrap:'wrap'}}>
            <label style={{display:'flex',alignItems:'center',gap:8,cursor:'pointer'}}>
              <input type="checkbox" checked={!!form.can_manage_users}
                onChange={e=>set('can_manage_users',e.target.checked)}/>
              <span className="sm">สามารถจัดการผู้ใช้งาน</span>
            </label>
            {!isNew && (
              <label style={{display:'flex',alignItems:'center',gap:8,cursor:'pointer'}}>
                <input type="checkbox" checked={!!form.active}
                  onChange={e=>set('active',e.target.checked)}/>
                <span className="sm">บัญชีใช้งานได้ (active)</span>
              </label>
            )}
          </div>

          <div className="btn-row" style={{marginTop:4}}>
            <button type="button" className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? <LoadingSpinner/> : isNew ? 'เพิ่มผู้ใช้' : 'บันทึก'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

/* ---------- modal รีเซ็ตรหัสผ่าน ---------- */
function ResetPassModal({ user, onClose }) {
  const [pass, setPass]   = useState('');
  const [done, setDone]   = useState(false);
  const [saving, setSaving] = useState(false);
  const [err, setErr]     = useState('');

  const submit = async e => {
    e.preventDefault();
    setSaving(true); setErr('');
    try {
      await apiFetch('/api/users.php?action=reset_pass&id='+user.id,
        { method:'POST', body: JSON.stringify({ password: pass }) });
      setDone(true);
    } catch(e) { setErr(e.message); }
    setSaving(false);
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-box" style={{maxWidth:400}} onClick={e=>e.stopPropagation()}>
        <div className="modal-head">
          <h3>รีเซ็ตรหัสผ่าน</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <div style={{padding:'0 24px 24px'}}>
          {done ? (
            <div style={{textAlign:'center',padding:'16px 0'}}>
              <div className="notice notice-ok"><Icon name="checkCircle"/>
                <div>รีเซ็ตรหัสผ่านของ <b>{user.display_name}</b> สำเร็จแล้ว</div>
              </div>
              <button className="btn btn-primary" style={{marginTop:16}} onClick={onClose}>ปิด</button>
            </div>
          ) : (
            <form onSubmit={submit} style={{display:'flex',flexDirection:'column',gap:14}}>
              <p className="sm muted">ตั้งรหัสผ่านใหม่สำหรับ <b>{user.display_name}</b> ({user.username})</p>
              {err && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}
              <div className="field">
                <label>รหัสผ่านใหม่ <span className="req">*</span></label>
                <input className="input" type="password" value={pass} onChange={e=>setPass(e.target.value)}
                  required minLength={6} autoComplete="new-password" autoFocus/>
                <span className="hint">อย่างน้อย 6 ตัวอักษร</span>
              </div>
              <div className="btn-row">
                <button type="button" className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
                <button type="submit" className="btn btn-primary" disabled={saving}>
                  {saving ? <LoadingSpinner/> : 'รีเซ็ตรหัสผ่าน'}
                </button>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}

/* ---------- หน้าหลัก ---------- */
function UserManagementPage({ currentUser, officers }) {
  const [users, setUsers]       = useState([]);
  const [loading, setLoading]   = useState(true);
  const [modal, setModal]       = useState(null); // null | {type:'edit'|'add'|'reset', user?}
  const [search, setSearch]     = useState('');
  const [filterRole, setFilterRole] = useState('');

  const load = () => {
    setLoading(true);
    apiFetch('/api/users.php')
      .then(setUsers).catch(console.error)
      .finally(() => setLoading(false));
  };
  useEffect(load, []);

  const handleSave = (saved, isNew) => {
    setUsers(us => isNew ? [...us, saved] : us.map(u => u.id === saved.id ? saved : u));
    setModal(null);
  };

  const deactivate = async (u) => {
    if (!confirm(`ปิดใช้งานบัญชี "${u.display_name}" (${u.username})?`)) return;
    try {
      await apiFetch('/api/users.php?id='+u.id, { method:'DELETE' });
      setUsers(us => us.map(x => x.id === u.id ? {...x, active:0} : x));
    } catch(e) { alert(e.message); }
  };

  const reactivate = async (u) => {
    try {
      const updated = await apiFetch('/api/users.php?id='+u.id,
        { method:'PATCH', body: JSON.stringify({ active: 1 }) });
      setUsers(us => us.map(x => x.id === u.id ? updated : x));
    } catch(e) { alert(e.message); }
  };

  const visible = users.filter(u => {
    if (filterRole && u.role !== filterRole) return false;
    if (search) {
      const q = search.toLowerCase();
      return u.username.toLowerCase().includes(q) || u.display_name.toLowerCase().includes(q);
    }
    return true;
  });

  return (
    <div className="fade-in">
      <PageHead title="จัดการผู้ใช้งาน" sub="เพิ่ม แก้ไข ปิดใช้งาน และรีเซ็ตรหัสผ่านบัญชีทั้งหมด">
        <button className="btn btn-primary" onClick={() => setModal({type:'add'})}>
          <Icon name="plus" style={{width:16,height:16}}/> เพิ่มผู้ใช้
        </button>
      </PageHead>

      {/* filter bar */}
      <div className="vcenter" style={{gap:10,marginBottom:16,flexWrap:'wrap'}}>
        <input className="input" style={{maxWidth:260}} placeholder="ค้นหาชื่อ / username…"
          value={search} onChange={e=>setSearch(e.target.value)}/>
        <select className="input" style={{maxWidth:200}} value={filterRole} onChange={e=>setFilterRole(e.target.value)}>
          <option value="">ทุกบทบาท</option>
          {ROLE_OPTS.map(o=><option key={o.v} value={o.v}>{o.l}</option>)}
        </select>
        <span className="faint sm">{visible.length} บัญชี</span>
      </div>

      <div className="card">
        {loading ? <div style={{padding:40,textAlign:'center'}}><LoadingSpinner/></div> : (
          <div className="table-wrap">
            <table className="tbl">
              <thead>
                <tr>
                  <th>ผู้ใช้</th>
                  <th>บทบาท</th>
                  <th>สิทธิ์พิเศษ</th>
                  <th>สถานะ</th>
                  <th style={{width:140}}></th>
                </tr>
              </thead>
              <tbody>
                {visible.length === 0 && (
                  <tr><td colSpan={5} style={{textAlign:'center',padding:32,color:'var(--ink-3)'}}>ไม่พบผู้ใช้</td></tr>
                )}
                {visible.map(u => (
                  <tr key={u.id} style={{opacity: u.active ? 1 : 0.45}}>
                    <td>
                      <div className="vcenter" style={{gap:10}}>
                        <span className="avatar avatar-sm">{u.init||u.username[0].toUpperCase()}</span>
                        <div>
                          <div style={{fontWeight:600}}>{u.display_name}</div>
                          <div className="faint tiny">{u.username}</div>
                        </div>
                      </div>
                    </td>
                    <td><span className={'badge ' + (ROLE_BADGE[u.role]||'badge')}>
                      {ROLE_OPTS.find(r=>r.v===u.role)?.l || u.role}
                    </span></td>
                    <td>
                      {u.can_manage_users ? <span className="badge badge-info"><Icon name="shieldCheck" style={{width:11,height:11}}/> จัดการผู้ใช้</span> : <span className="faint tiny">—</span>}
                    </td>
                    <td>
                      {u.active
                        ? <span className="badge badge-ok">ใช้งานได้</span>
                        : <span className="badge">ปิดใช้งาน</span>}
                    </td>
                    <td>
                      <div className="vcenter" style={{gap:6,justifyContent:'flex-end'}}>
                        <button className="icon-btn" title="แก้ไข"
                          onClick={() => setModal({type:'edit', user:u})}>
                          <Icon name="edit" style={{width:15,height:15}}/>
                        </button>
                        <button className="icon-btn" title="รีเซ็ตรหัสผ่าน"
                          onClick={() => setModal({type:'reset', user:u})}>
                          <Icon name="lock" style={{width:15,height:15}}/>
                        </button>
                        {u.id !== currentUser.id && (
                          u.active
                            ? <button className="icon-btn" title="ปิดใช้งาน"
                                onClick={() => deactivate(u)} style={{color:'var(--danger)'}}>
                                <Icon name="x" style={{width:15,height:15}}/>
                              </button>
                            : <button className="icon-btn" title="เปิดใช้งาน"
                                onClick={() => reactivate(u)} style={{color:'var(--ok)'}}>
                                <Icon name="checkCircle" style={{width:15,height:15}}/>
                              </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {modal?.type === 'add' && (
        <UserModal officers={officers} onSave={handleSave} onClose={() => setModal(null)}/>
      )}
      {modal?.type === 'edit' && (
        <UserModal user={modal.user} officers={officers} onSave={handleSave} onClose={() => setModal(null)}/>
      )}
      {modal?.type === 'reset' && (
        <ResetPassModal user={modal.user} onClose={() => setModal(null)}/>
      )}
    </div>
  );
}

Object.assign(window, { UserManagementPage });
