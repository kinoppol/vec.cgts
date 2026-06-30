/* ============================================================
   admin-users.jsx — จัดการผู้ใช้งานระบบ
   ============================================================ */

const ROLE_BADGE = {
  officer:          'badge',
  clerk:            'badge',
  head_secretary:   'badge-ok',
  dir_legal:        'badge-info',
  dir_admin:        'badge-warn',
  secretary:        'badge-maroon',
  deputy_secretary: 'badge-maroon',
  admin:            'badge-danger',
};

const OVERLAY_STYLE = {
  position:'fixed', inset:0, background:'rgba(20,10,12,.55)',
  display:'flex', alignItems:'center', justifyContent:'center',
  zIndex:200, padding:24,
};
const BOX_STYLE_BASE = {
  background:'var(--surface)', borderRadius:12,
  boxShadow:'0 8px 40px rgba(0,0,0,.35)',
  display:'flex', flexDirection:'column',
  width:'100%', maxHeight:'90vh',
};

/* ---------- helper: select ที่รองรับค่าเดิมที่ไม่อยู่ใน list ---------- */
function LookupSelect({ value, items, placeholder, onChange, style }) {
  const inList = items.some(x => x.name === value);
  return (
    <select className="input" value={value || ''} onChange={e => onChange(e.target.value)} style={style}>
      <option value="">{placeholder || '— เลือก —'}</option>
      {!inList && value && <option value={value}>{value} (ค่าเดิม)</option>}
      {items.map(x => <option key={x.id} value={x.name}>{x.name}</option>)}
    </select>
  );
}

/* ---------- modal เพิ่ม / แก้ไข ---------- */
function UserModal({ user, officers, roleLabels, isAdmin, isDirLegal, onSave, onAvatarChange, onClose }) {
  // dir_legal มอบหมายได้เฉพาะ head_secretary; admin เห็นทุก role
  const allowedRoles = isDirLegal && !isAdmin
    ? ['officer', 'head_secretary']
    : ROLE_ORDER;
  const roleOpts = [
    { v: '', l: 'ไม่กำหนดบทบาท (ยึดตามกลุ่ม)' },
    ...allowedRoles.map(v => ({ v, l: roleLabel(v, roleLabels) }))
  ];
  const isNew = !user?.id;
  const [form, setForm] = useState(user ? { ...user, password:'' } : {
    username:'', display_name:'', role:'',
    officer_id:'', can_manage_users:false, active:true, password:''
  });
  const [saving, setSaving] = useState(false);
  const [err, setErr]       = useState('');
  const [avatarBusy, setAvatarBusy] = useState(false);
  const fileRef = useRef(null);

  /* autocomplete เชื่อมโยงบุคลากร */
  const initOfficerName = (() => {
    const o = (officers||[]).find(o => o.id === (user?.officer_id||''));
    return o ? o.name : '';
  })();
  const [officerQuery, setOfficerQuery] = useState(initOfficerName);
  const [officerOpen,  setOfficerOpen]  = useState(false);
  const officerBoxRef = useRef(null);
  useEffect(() => {
    const onDoc = e => { if (officerBoxRef.current && !officerBoxRef.current.contains(e.target)) setOfficerOpen(false); };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, []);

  const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

  /* เมื่อเลือกบุคลากร ให้ auto-fill display_name จากชื่อบุคลากร */
  const handleOfficerChange = (oid) => {
    set('officer_id', oid);
    if (oid) {
      const off = (officers||[]).find(o => o.id === oid);
      if (off) { set('display_name', off.name); setOfficerQuery(off.name); }
    } else {
      setOfficerQuery('');
    }
    setOfficerOpen(false);
  };

  const pickAvatar = async e => {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { setErr('ไฟล์ภาพขนาดใหญ่เกิน 2 MB'); return; }
    setAvatarBusy(true); setErr('');
    try {
      const saved = await api.uploadAvatar(file, user.id);
      set('avatar_path', saved.avatar_path);
      onAvatarChange(saved);
    } catch(e) { setErr(e.message); }
    setAvatarBusy(false);
  };

  const removeAvatar = async () => {
    setAvatarBusy(true); setErr('');
    try {
      const saved = await api.removeAvatar(user.id);
      set('avatar_path', saved.avatar_path);
      onAvatarChange(saved);
    } catch(e) { setErr(e.message); }
    setAvatarBusy(false);
  };

  const submit = async e => {
    e.preventDefault();
    setSaving(true); setErr('');
    try {
      let saved;
      if (isNew) {
        saved = await apiFetch('/api/users.php', { method:'POST', body: JSON.stringify(form) });
      } else {
        const patch = { display_name:form.display_name, role:form.role,
                        officer_id:form.officer_id||null, active:form.active?1:0,
                        can_manage_users:form.can_manage_users?1:0 };
        saved = await apiFetch('/api/users.php?id='+user.id, { method:'PATCH', body: JSON.stringify(patch) });
      }
      onSave(saved, isNew);
    } catch(e) { setErr(e.message); }
    setSaving(false);
  };

  const linkedOfficer = (officers||[]).find(o => o.id === form.officer_id);

  return (
    <div style={OVERLAY_STYLE} onClick={onClose}>
      <div style={{...BOX_STYLE_BASE, maxWidth:480, overflow:'hidden'}} onClick={e=>e.stopPropagation()}>
        <div style={{padding:'20px 24px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between',flexShrink:0,background:'var(--surface)',borderRadius:'var(--r-lg) var(--r-lg) 0 0'}}>
          <h3 style={{margin:0,fontSize:17}}>{isNew ? 'เพิ่มผู้ใช้ใหม่' : 'แก้ไขผู้ใช้'}</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <form id="user-form" onSubmit={submit} style={{padding:'0 24px',display:'flex',flexDirection:'column',gap:14,overflowY:'auto',flex:1}}>
          {err && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}

          {!isNew && (
            <div className="vcenter" style={{gap:16}}>
              <div style={{position:'relative',width:64,height:64,flexShrink:0}}>
                <Avatar user={form} size="lg" style={{width:64,height:64,fontSize:20}}/>
                {avatarBusy && <div style={{position:'absolute',inset:0,display:'grid',placeItems:'center',background:'rgba(0,0,0,.35)',borderRadius:'50%'}}><LoadingSpinner/></div>}
              </div>
              <div style={{display:'flex',flexDirection:'column',gap:6}}>
                <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" style={{display:'none'}} onChange={pickAvatar}/>
                <button type="button" className="btn btn-outline btn-sm" onClick={()=>fileRef.current?.click()} disabled={avatarBusy}>
                  <Icon name="paperclip" style={{width:14,height:14}}/> เปลี่ยนภาพ
                </button>
                {form.avatar_path && (
                  <button type="button" className="btn btn-ghost btn-sm" onClick={removeAvatar} disabled={avatarBusy}>
                    <Icon name="x" style={{width:14,height:14}}/> ลบภาพ
                  </button>
                )}
                <span className="hint">JPG, PNG, WEBP ไม่เกิน 2 MB</span>
              </div>
            </div>
          )}

          <div className="field">
            <label>ชื่อผู้ใช้ <span className="req">*</span></label>
            <input className="input" value={form.username} onChange={e=>set('username',e.target.value)}
              disabled={!isNew && !isAdmin} required placeholder="a-z, 0-9, _"/>
          </div>

          {isNew && (
            <div className="field">
              <label>รหัสผ่าน <span className="req">*</span></label>
              <input className="input" type="password" value={form.password}
                onChange={e=>set('password',e.target.value)} required minLength={6} autoComplete="new-password"/>
              <span className="hint">อย่างน้อย 6 ตัวอักษร</span>
            </div>
          )}

          <div className="field">
            <label>อีเมล (สำหรับรับการแจ้งเตือน)</label>
            <input className="input" type="email" value={form.email||''}
              onChange={e=>set('email',e.target.value)} placeholder="officer@example.com"/>
          </div>

          <div className="form-grid" style={{gridTemplateColumns:'1fr 1fr',gap:14}}>
            <div className="field">
              <label>บทบาท</label>
              <select className="input" value={form.role} onChange={e=>set('role',e.target.value)}>
                {roleOpts.map(o=><option key={o.v} value={o.v}>{o.l}</option>)}
              </select>
            </div>
            <div className="field">
              <label>เชื่อมโยงกับบุคลากร</label>
              <div ref={officerBoxRef} style={{position:'relative'}}>
                <div className="vcenter" style={{gap:6}}>
                  <input className="input" style={{flex:1}}
                    placeholder="พิมพ์ชื่อบุคลากรเพื่อค้นหา…"
                    value={officerQuery}
                    onChange={e=>{ setOfficerQuery(e.target.value); setOfficerOpen(true); if(form.officer_id) set('officer_id',''); }}
                    onFocus={()=>setOfficerOpen(true)}/>
                  {form.officer_id && <button type="button" className="icon-btn" title="ล้าง" onClick={()=>handleOfficerChange('')}><Icon name="x" style={{width:15,height:15}}/></button>}
                </div>
                {officerOpen && (() => {
                  const q = officerQuery.trim().toLowerCase();
                  const matches = (officers||[]).filter(o =>
                    !q || o.name.toLowerCase().includes(q) || (o.group||'').toLowerCase().includes(q) || (o.id||'').toLowerCase().includes(q)
                  ).slice(0, 40);
                  return (
                    <div className="card" style={{position:'absolute',top:'100%',left:0,right:0,marginTop:4,maxHeight:240,overflowY:'auto',zIndex:50,boxShadow:'0 8px 24px rgba(0,0,0,.18)'}}>
                      {matches.length === 0
                        ? <div className="faint sm" style={{padding:'10px 12px'}}>ไม่พบบุคลากร</div>
                        : matches.map(o=>(
                            <div key={o.id} onClick={()=>handleOfficerChange(o.id)}
                              style={{padding:'8px 12px',cursor:'pointer',borderBottom:'1px solid var(--line)',background:form.officer_id===o.id?'var(--surface-2)':'transparent'}}
                              onMouseEnter={e=>e.currentTarget.style.background='var(--surface-2)'}
                              onMouseLeave={e=>e.currentTarget.style.background=form.officer_id===o.id?'var(--surface-2)':'transparent'}>
                              <div style={{fontWeight:500,fontSize:13}}>{o.name}</div>
                              {(o.group || o.role) && <div className="faint tiny">{[o.role,o.group].filter(Boolean).join(' · ')}</div>}
                            </div>
                          ))}
                    </div>
                  );
                })()}
              </div>
            </div>
          </div>

          {linkedOfficer && (
            <div className="notice" style={{background:'var(--surface-2)',border:'1px solid var(--border)',borderRadius:8,padding:'8px 12px',fontSize:13}}>
              <Icon name="users" style={{width:14,height:14,color:'var(--maroon)',flexShrink:0}}/>
              <span className="muted">ชื่อแสดง ตำแหน่ง และกลุ่มงานดึงจากข้อมูลบุคลากรโดยอัตโนมัติ</span>
            </div>
          )}

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

        </form>
        <div className="modal-f">
          <button type="button" className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
          <button type="submit" form="user-form" className="btn btn-primary" disabled={saving}>
            {saving ? <LoadingSpinner/> : isNew ? 'เพิ่มผู้ใช้' : 'บันทึก'}
          </button>
        </div>
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
    <div style={OVERLAY_STYLE} onClick={onClose}>
      <div style={{...BOX_STYLE_BASE, maxWidth:400, overflow:'hidden'}} onClick={e=>e.stopPropagation()}>
        <div style={{padding:'20px 24px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between',flexShrink:0,background:'var(--surface)',borderRadius:'var(--r-lg) var(--r-lg) 0 0'}}>
          <h3 style={{margin:0,fontSize:17}}>รีเซ็ตรหัสผ่าน</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <div style={{padding:'0 24px',overflowY:'auto',flex:1,display:'flex',flexDirection:'column',gap:14,paddingTop:16}}>
          {done ? (
            <div className="notice notice-ok"><Icon name="checkCircle"/>
              <div>รีเซ็ตรหัสผ่านของ <b>{user.display_name}</b> สำเร็จแล้ว</div>
            </div>
          ) : (
            <form id="reset-form" onSubmit={submit} style={{display:'flex',flexDirection:'column',gap:14}}>
              <p className="sm muted" style={{margin:0}}>ตั้งรหัสผ่านใหม่สำหรับ <b>{user.display_name}</b> ({user.username})</p>
              {err && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}
              <div className="field">
                <label>รหัสผ่านใหม่ <span className="req">*</span></label>
                <input className="input" type="password" value={pass} onChange={e=>setPass(e.target.value)}
                  required minLength={6} autoComplete="new-password" autoFocus/>
                <span className="hint">อย่างน้อย 6 ตัวอักษร</span>
              </div>
            </form>
          )}
        </div>
        <div className="modal-f">
          {done
            ? <button type="button" className="btn btn-primary" onClick={onClose}>ปิด</button>
            : <>
                <button type="button" className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
                <button type="submit" form="reset-form" className="btn btn-primary" disabled={saving}>
                  {saving ? <LoadingSpinner/> : 'รีเซ็ตรหัสผ่าน'}
                </button>
              </>}
        </div>
      </div>
    </div>
  );
}

/* ---------- หน้าหลัก ---------- */
function UserManagementPage({ currentUser, officers, roleLabels }) {
  const roleOpts = [
    ...ROLE_ORDER.map(v => ({ v, l: roleLabel(v, roleLabels) })),
    { v: '__null__', l: 'ไม่กำหนดบทบาท' }
  ];
  const [users, setUsers]           = useState([]);
  const [loading, setLoading]       = useState(true);
  const [modal, setModal]       = useState(null); // null | {type:'edit'|'add'|'reset'|'impersonate'|'deactivate', user?}
  const [search, setSearch]     = useState('');
  const [filterRole, setFilterRole] = useState('');
  const [filterGroup, setFilterGroup] = useState('');

  const load = () => {
    setLoading(true);
    apiFetch('/api/users.php')
      .then(setUsers).catch(console.error)
      .finally(() => setLoading(false));
  };
  useEffect(() => { load(); }, []);

  const handleSave = (saved, isNew) => {
    setUsers(us => isNew ? [...us, saved] : us.map(u => u.id === saved.id ? saved : u));
    setModal(null);
  };

  const handleAvatarChange = (saved) => {
    setUsers(us => us.map(u => u.id === saved.id ? { ...u, ...saved } : u));
  };

  const handleImpersonate = async (u) => {
    setModal({ type: 'impersonate', user: u });
  };

  const doImpersonate = async (u) => {
    try {
      await api.impersonate(u.id);
      window.location.reload();
    } catch(e) { alert(e.message); }
  };

  const deactivate = async (u) => {
    setModal({ type: 'deactivate', user: u });
  };

  const doDeactivate = async (u) => {
    try {
      await apiFetch('/api/users.php?id='+u.id, { method:'DELETE' });
      setUsers(us => us.map(x => x.id === u.id ? {...x, active:0} : x));
      setModal(null);
    } catch(e) { alert(e.message); }
  };

  const reactivate = async (u) => {
    try {
      const updated = await apiFetch('/api/users.php?id='+u.id,
        { method:'PATCH', body: JSON.stringify({ active: 1 }) });
      setUsers(us => us.map(x => x.id === u.id ? updated : x));
    } catch(e) { alert(e.message); }
  };

  const [showInactive, setShowInactive] = useState(false);

  // รายชื่อกลุ่มที่สังกัด (distinct จาก users)
  const groupOpts = [...new Set(users.map(u => u.group_name).filter(Boolean))]
    .sort((a,b)=>a.localeCompare(b,'th'));

  const filtered = users.filter(u => {
    if (filterRole === '__null__' && u.role) return false;
    if (filterRole && filterRole !== '__null__' && u.role !== filterRole) return false;
    if (filterGroup === '__none__' && u.group_name) return false;
    if (filterGroup && filterGroup !== '__none__' && u.group_name !== filterGroup) return false;
    if (search) {
      const q = search.toLowerCase();
      return u.username.toLowerCase().includes(q) || u.display_name.toLowerCase().includes(q);
    }
    return true;
  });
  const activeUsers   = filtered.filter(u => u.active);
  const inactiveUsers = filtered.filter(u => !u.active);

  const UserRow = ({ u }) => (
    <tr key={u.id}>
      <td>
        <div className="vcenter" style={{gap:10}}>
          <Avatar user={{...u, init: u.init || u.username[0].toUpperCase()}} size="sm"/>
          <div>
            <div style={{fontWeight:600}}>{u.display_name}</div>
            <div className="faint tiny">{u.username}</div>
          </div>
        </div>
      </td>
      <td>
        {u.role
          ? <span className={'badge ' + (ROLE_BADGE[u.role]||'badge')}>{roleLabel(u.role, roleLabels)}</span>
          : <span className="badge" style={{color:'var(--ink-3)'}}>
              ไม่กำหนดบทบาท
              {u.group_role && <span style={{color:'var(--ink-2)',marginLeft:4}}>({roleLabel(u.group_role, roleLabels)})</span>}
            </span>
        }
      </td>
      <td>
        {u.can_manage_users ? <span className="badge badge-info"><Icon name="shieldCheck" style={{width:11,height:11}}/> จัดการผู้ใช้</span> : <span className="faint tiny">—</span>}
      </td>
      <td>
        <div className="vcenter" style={{gap:6,justifyContent:'flex-end'}}>
          {currentUser.role === 'admin' && u.id !== currentUser.id && u.role !== 'admin' && u.active && (
            <button className="icon-btn" title="สวมสิทธิ์ผู้ใช้คนนี้"
              onClick={() => handleImpersonate(u)}
              style={{color:'var(--maroon)'}}>
              <Icon name="eye" style={{width:15,height:15}}/>
            </button>
          )}
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
              : <button className="icon-btn" title="เปิดใช้งานอีกครั้ง"
                  onClick={() => reactivate(u)} style={{color:'var(--ok)'}}>
                  <Icon name="checkCircle" style={{width:15,height:15}}/>
                </button>
          )}
        </div>
      </td>
    </tr>
  );

  const UsersTable = ({ rows, emptyText }) => (
    <div className="table-wrap">
      <table className="tbl">
        <thead>
          <tr>
            <th>ผู้ใช้</th>
            <th>บทบาท</th>
            <th>สิทธิ์พิเศษ</th>
            <th style={{width:120}}></th>
          </tr>
        </thead>
        <tbody>
          {rows.length === 0
            ? <tr><td colSpan={4} style={{textAlign:'center',padding:32,color:'var(--ink-3)'}}>{emptyText}</td></tr>
            : rows.map(u => <UserRow key={u.id} u={u}/>)}
        </tbody>
      </table>
    </div>
  );

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
          {roleOpts.map(o=><option key={o.v} value={o.v}>{o.l}</option>)}
        </select>
        <select className="input" style={{maxWidth:220}} value={filterGroup} onChange={e=>setFilterGroup(e.target.value)}>
          <option value="">ทุกกลุ่ม</option>
          {groupOpts.map(g=><option key={g} value={g}>{g}</option>)}
          <option value="__none__">— ไม่สังกัดกลุ่ม —</option>
        </select>
        <span className="faint sm">{activeUsers.length} บัญชีที่ใช้งาน</span>
      </div>

      {/* ตารางผู้ใช้ที่ใช้งานได้ */}
      <div className="card">
        {loading
          ? <div style={{padding:40,textAlign:'center'}}><LoadingSpinner/></div>
          : <UsersTable rows={activeUsers} emptyText="ไม่พบผู้ใช้"/>}
      </div>

      {/* ส่วนผู้ใช้ที่ปิดใช้งานแล้ว */}
      {!loading && (
        <div style={{marginTop:16}}>
          <button
            className="btn btn-ghost btn-sm vcenter"
            style={{gap:6,color:'var(--ink-3)',fontSize:13}}
            onClick={() => setShowInactive(v => !v)}
          >
            <Icon name={showInactive ? 'chevronDown' : 'chevronRight'} style={{width:14,height:14}}/>
            บัญชีที่ปิดใช้งานแล้ว ({inactiveUsers.length})
          </button>
          {showInactive && (
            <div className="card" style={{marginTop:8,opacity:0.75}}>
              <UsersTable rows={inactiveUsers} emptyText="ไม่มีบัญชีที่ปิดใช้งาน"/>
            </div>
          )}
        </div>
      )}

      {modal?.type === 'add' && (
        <UserModal officers={officers} roleLabels={roleLabels} isAdmin={currentUser?.role==='admin'}
          isDirLegal={currentUser?.role==='dir_legal'}
          onSave={handleSave} onClose={() => setModal(null)}/>
      )}
      {modal?.type === 'edit' && (
        <UserModal user={modal.user} officers={officers} roleLabels={roleLabels} isAdmin={currentUser?.role==='admin'}
          isDirLegal={currentUser?.role==='dir_legal'}
          onSave={handleSave} onAvatarChange={handleAvatarChange} onClose={() => setModal(null)}/>
      )}
      {modal?.type === 'reset' && (
        <ResetPassModal user={modal.user} onClose={() => setModal(null)}/>
      )}

      {modal?.type === 'impersonate' && (() => { const u = modal.user; return (
        <div style={{position:'fixed',inset:0,background:'rgba(20,10,12,.55)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:300,padding:24}}
          onClick={()=>setModal(null)}>
          <div style={{background:'var(--surface)',borderRadius:14,boxShadow:'0 8px 40px rgba(0,0,0,.35)',width:'100%',maxWidth:400,padding:'28px 28px 24px'}}
            onClick={e=>e.stopPropagation()}>
            <div style={{display:'flex',flexDirection:'column',alignItems:'center',gap:14,textAlign:'center'}}>
              <div style={{width:56,height:56,borderRadius:'50%',background:'color-mix(in srgb,var(--maroon) 12%,transparent)',display:'grid',placeItems:'center'}}>
                <Icon name="eye" style={{width:26,height:26,color:'var(--maroon)'}}/>
              </div>
              <div>
                <div style={{fontWeight:700,fontSize:17,marginBottom:6}}>สวมสิทธิ์ผู้ใช้</div>
                <div className="muted" style={{fontSize:13,lineHeight:1.6}}>
                  ระบบจะเข้าสู่การใช้งานในมุมมองของ<br/>
                  <b style={{color:'var(--ink)'}}>{u.display_name}</b>
                  <span className="faint"> ({u.username})</span><br/>
                  ทันที — คลิก <b>คืนสิทธิ์ Admin</b> ที่แถบด้านบนเพื่อออก
                </div>
              </div>
              <div style={{display:'flex',gap:10,marginTop:4}}>
                <button className="btn btn-ghost" onClick={()=>setModal(null)}>ยกเลิก</button>
                <button className="btn btn-primary" style={{background:'var(--maroon)'}}
                  onClick={()=>doImpersonate(u)}>
                  <Icon name="eye" style={{width:14,height:14}}/> สวมสิทธิ์เลย
                </button>
              </div>
            </div>
          </div>
        </div>
      ); })()}

      {modal?.type === 'deactivate' && (() => { const u = modal.user; return (
        <div style={{position:'fixed',inset:0,background:'rgba(20,10,12,.55)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:300,padding:24}}
          onClick={()=>setModal(null)}>
          <div style={{background:'var(--surface)',borderRadius:14,boxShadow:'0 8px 40px rgba(0,0,0,.35)',width:'100%',maxWidth:400,padding:'28px 28px 24px'}}
            onClick={e=>e.stopPropagation()}>
            <div style={{display:'flex',flexDirection:'column',alignItems:'center',gap:14,textAlign:'center'}}>
              <div style={{width:56,height:56,borderRadius:'50%',background:'var(--danger-bg,#fef2f2)',display:'grid',placeItems:'center'}}>
                <Icon name="x" style={{width:26,height:26,color:'var(--danger,#dc2626)'}}/>
              </div>
              <div>
                <div style={{fontWeight:700,fontSize:17,marginBottom:6}}>ปิดใช้งานบัญชี</div>
                <div className="muted" style={{fontSize:13,lineHeight:1.6}}>
                  ต้องการปิดใช้งานบัญชีของ<br/>
                  <b style={{color:'var(--ink)'}}>{u.display_name}</b>
                  <span className="faint"> ({u.username})</span><br/>
                  บัญชีจะไม่สามารถเข้าสู่ระบบได้จนกว่าจะเปิดใช้งานอีกครั้ง
                </div>
              </div>
              <div style={{display:'flex',gap:10,marginTop:4}}>
                <button className="btn btn-ghost" onClick={()=>setModal(null)}>ยกเลิก</button>
                <button className="btn btn-primary" style={{background:'var(--danger,#dc2626)'}}
                  onClick={()=>doDeactivate(u)}>
                  <Icon name="x" style={{width:14,height:14}}/> ปิดใช้งาน
                </button>
              </div>
            </div>
          </div>
        </div>
      ); })()}
    </div>
  );
}

Object.assign(window, { LookupSelect, UserManagementPage });
