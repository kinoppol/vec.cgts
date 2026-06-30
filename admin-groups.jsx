/* ============================================================
   admin-groups.jsx — จัดการกลุ่ม (admin)
   ============================================================ */

function GroupFormModal({ group, onSave, onClose }) {
  const [name,       setName]       = useState(group ? group.name : "");
  const [leaderRole, setLeaderRole] = useState(group ? (group.leader_role||"") : "");
  const [deptName,   setDeptName]   = useState(group ? (group.dept_name||"") : "");
  const [recvPrefix, setRecvPrefix] = useState(group ? (group.recv_prefix||"") : "");
  const [lookupGroups, setLookupGroups] = useState([]);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState("");

  React.useEffect(() => {
    api.getLookups('group_name').then(setLookupGroups).catch(() => {});
  }, []);

  async function submit(e) {
    e.preventDefault();
    if (!name.trim()) { setErr("กรุณาระบุชื่อกลุ่ม"); return; }
    setSaving(true); setErr("");
    try {
      const payload = { name: name.trim(), leader_role: leaderRole||null, dept_name: deptName||null, recv_prefix: recvPrefix.trim()||null };
      const result = group
        ? await api.updateGroup(group.id, payload)
        : await api.createGroup(payload);
      onSave(result);
    } catch(e) { setErr(e.message); setSaving(false); }
  }

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal" style={{maxWidth:420}} onClick={e=>e.stopPropagation()}>
        <div className="modal-head">
          <h3>{group ? "แก้ไขกลุ่ม" : "เพิ่มกลุ่มใหม่"}</h3>
          <button className="btn-close" onClick={onClose}><Icon name="x"/></button>
        </div>
        <form onSubmit={submit} style={{padding:"20px 24px",display:"flex",flexDirection:"column",gap:14}}>
          <div className="field">
            <label className="label">ชื่อกลุ่ม <span className="req">*</span></label>
            <input className="input" value={name} onChange={e=>setName(e.target.value)} autoFocus placeholder="เช่น กลุ่มงานวินัย"/>
          </div>
          <div className="field">
            <label className="label">กลุ่มงาน (สายงาน) <span className="tiny muted">— สำหรับกรองบุคลากรในระบบ</span></label>
            {lookupGroups.length > 0
              ? <select className="select" value={deptName} onChange={e=>setDeptName(e.target.value)}>
                  <option value="">— ไม่กำหนด —</option>
                  {lookupGroups.map(g=><option key={g.id||g.name} value={g.name}>{g.name}</option>)}
                </select>
              : <input className="input" value={deptName} placeholder="เช่น กลุ่มงานวินัย อุทธรณ์" onChange={e=>setDeptName(e.target.value)}/>
            }
            <div className="tiny faint" style={{marginTop:4}}>บุคลากรในกลุ่มนี้จะได้รับกลุ่มงานนี้โดยอัตโนมัติ</div>
          </div>
          <div className="field">
            <label className="label">PREFIX เลขรับภายในกลุ่ม</label>
            <input className="input" value={recvPrefix} maxLength={20}
              onChange={e=>setRecvPrefix(e.target.value)} placeholder="เช่น วน, กม, นต"/>
            <div className="tiny faint" style={{marginTop:4}}>
              ใช้ออกเลขรับเมื่อเกษียนเรื่องเข้ากลุ่ม รูปแบบ <b>{(recvPrefix.trim()||'PREFIX')}-{new Date().getFullYear()+543}-00001</b>
            </div>
          </div>
          <div className="field">
            <label className="label">บทบาทเฉพาะสำหรับหัวหน้ากลุ่ม</label>
            <select className="select" value={leaderRole} onChange={e=>setLeaderRole(e.target.value)}>
              <option value="">— ไม่กำหนด (เหมือนสมาชิกทั่วไป) —</option>
              {ROLE_ORDER.map(r => (
                <option key={r} value={r}>{DEFAULT_ROLE_LABELS[r]||r}</option>
              ))}
            </select>
            <div className="tiny faint" style={{marginTop:4}}>เมื่อแต่งตั้งหัวหน้ากลุ่ม ระบบจะกำหนดบทบาทนี้ให้โดยอัตโนมัติ</div>
          </div>
          {err && <div className="notice notice-danger"><Icon name="alert"/><span>{err}</span></div>}
          <div className="modal-foot">
            <button type="button" className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
            <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? "กำลังบันทึก…" : "บันทึก"}</button>
          </div>
        </form>
      </div>
    </div>
  );
}

function AddMemberModal({ group, allUsers, members, onAdd, onClose }) {
  const [search, setSearch]   = useState("");
  const hasRoles = group.roles && group.roles.length > 0;
  const [selRole, setSelRole] = useState("");
  const memberIds = new Set(members.map(m => m.id));
  const available = allUsers.filter(u =>
    !memberIds.has(u.id) &&
    (u.display_name.includes(search) || u.username.includes(search) || (u.job_title||"").includes(search))
  );

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal" style={{maxWidth:460}} onClick={e=>e.stopPropagation()}>
        <div className="modal-head">
          <h3>เพิ่มสมาชิก <span className="muted">— {group.name}</span></h3>
          <button className="btn-close" onClick={onClose}><Icon name="x"/></button>
        </div>
        <div style={{padding:"16px 24px 0",display:"flex",flexDirection:"column",gap:10}}>
          <input className="input" placeholder="ค้นหาชื่อ / username…" value={search} onChange={e=>setSearch(e.target.value)} autoFocus/>
          {hasRoles && (
            <div>
              <label className="label">บทบาท <span className="badge badge-maroon" style={{marginLeft:4,fontSize:10}}>กำหนดโดยกลุ่ม</span></label>
              <select className="select" value={selRole} onChange={e=>setSelRole(e.target.value)}>
                <option value="">ไม่กำหนดบทบาท (ยึดตามกลุ่ม)</option>
                {group.roles.map(r => <option key={r} value={r}>{DEFAULT_ROLE_LABELS[r]||r}</option>)}
              </select>
            </div>
          )}
        </div>
        <div style={{maxHeight:300,overflowY:"auto",padding:"8px 24px 20px"}}>
          {available.length === 0 && <div className="faint tiny" style={{padding:"16px 0",textAlign:"center"}}>ไม่พบผู้ใช้ที่ยังไม่ได้เป็นสมาชิก</div>}
          {available.map(u => (
            <div key={u.id} className="between" style={{padding:"10px 0",borderBottom:"1px solid var(--line)"}}>
              <div className="vcenter" style={{gap:10}}>
                <span className="avatar avatar-sm">{u.init || u.display_name[0]}</span>
                <div>
                  <div className="sm" style={{fontWeight:500}}>{u.display_name}</div>
                  <div className="tiny faint">{u.username} · {DEFAULT_ROLE_LABELS[u.role]||u.role}</div>
                </div>
              </div>
              <button className="btn btn-sm btn-outline"
                onClick={()=>onAdd(u, selRole||null)}>+ เพิ่ม</button>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function GroupsPage({ currentUser }) {
  const [groups, setGroups]             = useState([]);
  const [allUsers, setAllUsers]         = useState([]);
  const [selId, setSelId]               = useState(null);
  const [detail, setDetail]             = useState(null);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [loading, setLoading]           = useState(true);
  const [showForm, setShowForm]         = useState(false);
  const [editGroup, setEditGroup]       = useState(null);
  const [showAddMember, setShowAddMember] = useState(false);
  const [delConfirm, setDelConfirm]     = useState(null);
  const [addingRole, setAddingRole]     = useState(false);
  const [newRole, setNewRole]           = useState("");

  useEffect(() => {
    Promise.all([api.getGroups(), apiFetch('/api/users.php')])
      .then(([g, u]) => { setGroups(g); setAllUsers(u); })
      .catch(e => alert(e.message))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (!selId) { setDetail(null); return; }
    setLoadingDetail(true);
    api.getGroupMembers(selId)
      .then(setDetail)
      .catch(e => alert(e.message))
      .finally(() => setLoadingDetail(false));
  }, [selId]);

  function handleGroupSaved(grp) {
    setGroups(gs => {
      const idx = gs.findIndex(g => g.id === grp.id);
      return idx >= 0 ? gs.map(g => g.id === grp.id ? {...g, ...grp} : g) : [...gs, grp];
    });
    setShowForm(false); setEditGroup(null);
    if (grp.id === selId) setDetail(d => d ? {...d, name: grp.name, leader_role: grp.leader_role??d.leader_role} : d);
  }

  async function handleDelete(grp) {
    try {
      await api.deleteGroup(grp.id);
      setGroups(gs => gs.filter(g => g.id !== grp.id));
      if (selId === grp.id) { setSelId(null); setDetail(null); }
    } catch(e) { alert(e.message); }
    setDelConfirm(null);
  }

  async function handleSetLeader(userId) {
    if (!detail) return;
    const newLeaderId = (detail.leader_id === userId) ? null : userId;
    try {
      const updated = await api.updateGroup(detail.id, { leader_id: newLeaderId });
      setDetail(d => ({...d, leader_id: updated.leader_id, leader_name: updated.leader_name, leader_init: updated.leader_init}));
      setGroups(gs => gs.map(g => g.id === detail.id ? {...g, ...updated} : g));
    } catch(e) { alert(e.message); }
  }

  async function handleAddMember(user, role) {
    if (!detail) return;
    try {
      await api.addGroupMember(detail.id, user.id, role);
      const updatedUser = role ? {...user, role} : user;
      setDetail(d => ({...d, members: [...(d.members||[]), updatedUser]}));
      setGroups(gs => gs.map(g => g.id === detail.id ? {...g, member_count: (g.member_count||0)+1} : g));
      setAllUsers(us => us.map(u => u.id === user.id ? {...updatedUser, group_name: detail.name} : u));
    } catch(e) { alert(e.message); }
    setShowAddMember(false);
  }

  async function handleRemoveMember(member) {
    if (!detail) return;
    try {
      await api.removeGroupMember(detail.id, member.id);
      setDetail(d => ({
        ...d,
        members: d.members.filter(m => m.id !== member.id),
        leader_id:   d.leader_id === member.id ? null : d.leader_id,
        leader_name: d.leader_id === member.id ? null : d.leader_name,
      }));
      setGroups(gs => gs.map(g => g.id === detail.id ? {...g, member_count: Math.max(0,(g.member_count||1)-1)} : g));
      setAllUsers(us => us.map(u => u.id === member.id ? {...u, group_name: null} : u));
    } catch(e) { alert(e.message); }
  }

  async function handleAddRole() {
    if (!newRole || !detail) return;
    try {
      const res = await api.addGroupRole(detail.id, newRole);
      setDetail(d => ({...d, roles: res.roles}));
      setGroups(gs => gs.map(g => g.id === detail.id ? {...g, roles: res.roles} : g));
      setNewRole(""); setAddingRole(false);
    } catch(e) { alert(e.message); }
  }

  async function handleRemoveRole(role) {
    if (!detail) return;
    try {
      const res = await api.removeGroupRole(detail.id, role);
      setDetail(d => ({...d, roles: res.roles}));
      setGroups(gs => gs.map(g => g.id === detail.id ? {...g, roles: res.roles} : g));
    } catch(e) { alert(e.message); }
  }

  if (loading) return <LoadingSpinner/>;

  const usedRoles = detail ? (detail.roles||[]) : [];
  const availableRoles = ROLE_ORDER.filter(r => r !== 'admin' && !usedRoles.includes(r));

  return (
    <div className="fade-in">
      <PageHead title="จัดการกลุ่ม" sub="เพิ่ม ลบ แก้ไขกลุ่ม กำหนดบทบาท จัดการสมาชิก และแต่งตั้งหัวหน้ากลุ่ม">
        <button className="btn btn-primary" onClick={()=>{ setEditGroup(null); setShowForm(true); }}>
          <Icon name="plus" style={{width:16,height:16}}/> เพิ่มกลุ่ม
        </button>
      </PageHead>

      <div style={{display:"grid",gridTemplateColumns:"300px 1fr",gap:18,alignItems:"start"}}>
        {/* ── รายการกลุ่ม ── */}
        <div className="card" style={{position:"sticky",top:80}}>
          <div className="card-h"><h3>กลุ่มทั้งหมด</h3><span className="badge">{groups.length}</span></div>
          <div style={{maxHeight:520,overflowY:"auto"}}>
            {groups.length === 0 && <div className="faint tiny" style={{padding:"20px 16px",textAlign:"center"}}>ยังไม่มีกลุ่ม</div>}
            {groups.map(g => (
              <div key={g.id} onClick={() => setSelId(g.id)} style={{
                  display:"flex", alignItems:"center", gap:10, padding:"12px 16px", cursor:"pointer",
                  background: selId===g.id ? "var(--maroon-50)" : "transparent",
                  borderBottom:"1px solid var(--line)", transition:"background .12s",
                }}>
                <div style={{flex:1,minWidth:0}}>
                  <div style={{fontWeight:selId===g.id?600:500,fontSize:14,color:selId===g.id?"var(--accent)":"var(--ink)"}}>{g.name}</div>
                  <div className="tiny faint" style={{marginTop:2}}>
                    {g.member_count} คน
                    {g.roles && g.roles.length > 0 && <span> · {g.roles.map(r=>DEFAULT_ROLE_LABELS[r]||r).join(", ")}</span>}
                  </div>
                </div>
                <div style={{display:"flex",gap:4,flexShrink:0}} onClick={e=>e.stopPropagation()}>
                  <button className="btn btn-ghost btn-sm" title="แก้ไขชื่อ"
                    onClick={()=>{ setEditGroup(g); setShowForm(true); }}>
                    <Icon name="edit" style={{width:14,height:14}}/>
                  </button>
                  <button className="btn btn-ghost btn-sm" title="ลบกลุ่ม"
                    style={{color:"var(--sla-r)"}} onClick={()=>setDelConfirm(g)}>
                    <Icon name="trash" style={{width:14,height:14}}/>
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* ── รายละเอียดกลุ่ม ── */}
        {!selId && (
          <div className="card card-pad" style={{display:"flex",alignItems:"center",justifyContent:"center",minHeight:200}}>
            <div style={{textAlign:"center",color:"var(--ink-3)"}}>
              <Icon name="users" style={{width:40,height:40,opacity:.3,marginBottom:12}}/>
              <div>เลือกกลุ่มเพื่อดูรายละเอียด</div>
            </div>
          </div>
        )}

        {selId && (
          <div style={{display:"flex",flexDirection:"column",gap:14}}>
            {loadingDetail && <LoadingSpinner/>}
            {!loadingDetail && detail && (
              <>
                {/* บทบาทของกลุ่ม */}
                <div className="card card-pad">
                  <div className="between" style={{marginBottom:12}}>
                    <h3 style={{fontSize:15,margin:0}}>บทบาทของกลุ่ม</h3>
                    {!addingRole && availableRoles.length > 0 && (
                      <button className="btn btn-sm btn-outline" onClick={()=>setAddingRole(true)}>
                        <Icon name="plus" style={{width:13,height:13}}/> เพิ่มบทบาท
                      </button>
                    )}
                  </div>
                  <div style={{display:"flex",flexWrap:"wrap",gap:8,marginBottom: addingRole ? 12 : 0}}>
                    {(detail.roles||[]).length === 0 && !addingRole && (
                      <span className="faint tiny">ยังไม่ได้กำหนดบทบาท — สมาชิกที่เพิ่มเข้ามาจะไม่ถูกเปลี่ยน role</span>
                    )}
                    {(detail.roles||[]).map(r => (
                      <span key={r} className="badge badge-maroon" style={{display:"inline-flex",alignItems:"center",gap:5,padding:"4px 10px"}}>
                        {DEFAULT_ROLE_LABELS[r]||r}
                        <button style={{background:"none",border:"none",cursor:"pointer",padding:0,lineHeight:1,color:"inherit",opacity:.75}}
                          onClick={()=>handleRemoveRole(r)} title="ถอดบทบาทนี้">
                          <Icon name="x" style={{width:11,height:11}}/>
                        </button>
                      </span>
                    ))}
                  </div>
                  {addingRole && (
                    <div style={{display:"flex",gap:8,marginTop:4}}>
                      <select className="select" style={{flex:1}} value={newRole} onChange={e=>setNewRole(e.target.value)} autoFocus>
                        <option value="">— เลือกบทบาท —</option>
                        {availableRoles.map(r => <option key={r} value={r}>{DEFAULT_ROLE_LABELS[r]||r}</option>)}
                      </select>
                      <button className="btn btn-primary btn-sm" onClick={handleAddRole} disabled={!newRole}>เพิ่ม</button>
                      <button className="btn btn-ghost btn-sm" onClick={()=>{ setAddingRole(false); setNewRole(""); }}>ยกเลิก</button>
                    </div>
                  )}
                  {(detail.roles||[]).length > 0 && (
                    <div className="notice notice-info" style={{marginTop:12}}>
                      <Icon name="info"/>
                      <span>เมื่อเพิ่มสมาชิกใหม่ จะสามารถเลือกบทบาทจากรายการนี้ได้</span>
                    </div>
                  )}
                </div>

                {/* หัวหน้ากลุ่ม */}
                <div className="card card-pad" style={{display:"grid",gap:14}}>
                  <h3 style={{fontSize:15,margin:0}}>หัวหน้ากลุ่ม</h3>

                  {/* บทบาทสำหรับหัวหน้า */}
                  <div className="field" style={{margin:0}}>
                    <label style={{fontSize:13}}>บทบาทที่หัวหน้ากลุ่มจะได้รับ</label>
                    <select className="select" style={{fontSize:13}}
                      value={detail.leader_role||""}
                      onChange={async e => {
                        const v = e.target.value||null;
                        try {
                          await api.updateGroup(detail.id, { leader_role: v });
                          setDetail(d => ({...d, leader_role: v}));
                        } catch(ex) { alert(ex.message); }
                      }}>
                      <option value="">— ไม่กำหนด (เหมือนสมาชิก) —</option>
                      {ROLE_ORDER.map(r => (
                        <option key={r} value={r}>{DEFAULT_ROLE_LABELS[r]||r}</option>
                      ))}
                    </select>
                  </div>

                  {detail.leader_id ? (
                    <div className="between">
                      <div className="vcenter" style={{gap:10}}>
                        <span className="avatar">{detail.leader_init || (detail.leader_name||"?")[0]}</span>
                        <div>
                          <div style={{fontWeight:600}}>{detail.leader_name}</div>
                          <div className="tiny faint">
                            หัวหน้ากลุ่ม {detail.name}
                            {detail.leader_role && <> · {DEFAULT_ROLE_LABELS[detail.leader_role]||detail.leader_role}</>}
                          </div>
                        </div>
                      </div>
                      <button className="btn btn-outline btn-sm" onClick={()=>handleSetLeader(detail.leader_id)}>ถอดหัวหน้า</button>
                    </div>
                  ) : (
                    <div className="notice notice-info">
                      <Icon name="info"/>
                      <span>ยังไม่มีหัวหน้ากลุ่ม — กดปุ่ม "แต่งตั้ง" ที่สมาชิกด้านล่าง</span>
                    </div>
                  )}
                </div>

                {/* สมาชิก */}
                <div className="card">
                  <div className="card-h">
                    <h3>สมาชิก <span className="badge" style={{marginLeft:6}}>{(detail.members||[]).length} คน</span></h3>
                    <button className="btn btn-sm btn-outline" onClick={()=>setShowAddMember(true)}>
                      <Icon name="plus" style={{width:14,height:14}}/> เพิ่มสมาชิก
                    </button>
                  </div>
                  <div>
                    {(!detail.members || detail.members.length === 0) && (
                      <div className="faint tiny" style={{padding:"20px 16px",textAlign:"center"}}>ยังไม่มีสมาชิกในกลุ่มนี้</div>
                    )}
                    {(detail.members||[]).map((m,i) => (
                      <div key={m.id} style={{display:"flex",alignItems:"center",gap:12,padding:"12px 16px",borderBottom:i<detail.members.length-1?"1px solid var(--line)":"none"}}>
                        <span className="avatar avatar-sm">{m.init || m.display_name[0]}</span>
                        <div style={{flex:1}}>
                          <div style={{fontWeight:500,fontSize:14}}>{m.display_name}</div>
                          <div className="tiny faint">{m.username} · {DEFAULT_ROLE_LABELS[m.role]||m.role}</div>
                        </div>
                        {detail.leader_id === m.id
                          ? <span className="badge badge-maroon" style={{flexShrink:0}}>หัวหน้า</span>
                          : <button className="btn btn-ghost btn-sm" style={{fontSize:11,flexShrink:0}} onClick={()=>handleSetLeader(m.id)}>แต่งตั้งหัวหน้า</button>
                        }
                        <button className="btn btn-ghost btn-sm" style={{color:"var(--sla-r)",flexShrink:0}}
                          onClick={()=>handleRemoveMember(m)} title="นำออกจากกลุ่ม">
                          <Icon name="x" style={{width:13,height:13}}/>
                        </button>
                      </div>
                    ))}
                  </div>
                </div>
              </>
            )}
          </div>
        )}
      </div>

      {/* Modals */}
      {showForm && (
        <GroupFormModal group={editGroup} onSave={handleGroupSaved} onClose={()=>{ setShowForm(false); setEditGroup(null); }}/>
      )}

      {showAddMember && detail && (
        <AddMemberModal
          group={detail}
          allUsers={allUsers}
          members={detail.members||[]}
          onAdd={handleAddMember}
          onClose={()=>setShowAddMember(false)}
        />
      )}

      {delConfirm && (
        <div className="modal-overlay" onClick={()=>setDelConfirm(null)}>
          <div className="modal" style={{maxWidth:380}} onClick={e=>e.stopPropagation()}>
            <div className="modal-head"><h3>ยืนยันลบกลุ่ม</h3></div>
            <div style={{padding:"20px 24px"}}>
              <p>ต้องการลบกลุ่ม <b>{delConfirm.name}</b> ใช่หรือไม่?</p>
              <p className="sm muted" style={{marginTop:8}}>สมาชิกทั้งหมดจะถูกถอดออกจากกลุ่มนี้ แต่บัญชีผู้ใช้จะยังคงอยู่</p>
            </div>
            <div className="modal-foot">
              <button className="btn btn-ghost" onClick={()=>setDelConfirm(null)}>ยกเลิก</button>
              <button className="btn btn-danger" onClick={()=>handleDelete(delConfirm)}>ลบกลุ่ม</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

/* ── MyGroupPage — หัวหน้ากลุ่มดูและมอบบทบาทสมาชิก ── */
function MyGroupPage({ currentUser }) {
  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState({});

  useEffect(() => {
    if (!currentUser?.leader_of_group) { setLoading(false); return; }
    api.getGroupMembers(currentUser.leader_of_group.id)
      .then(setDetail)
      .catch(e => alert(e.message))
      .finally(() => setLoading(false));
  }, []);

  const handleRoleChange = async (member, newRole) => {
    setSaving(s => ({...s, [member.id]: true}));
    try {
      await api.setMemberRole(detail.id, member.id, newRole||null);
      setDetail(d => ({
        ...d,
        members: d.members.map(m => m.id === member.id ? {...m, role: newRole||null} : m)
      }));
    } catch(e) { alert(e.message); }
    setSaving(s => ({...s, [member.id]: false}));
  };

  if (loading) return <LoadingSpinner/>;
  if (!detail) return (
    <div className="card card-pad fade-in">
      <div className="faint" style={{textAlign:"center",padding:32}}>ไม่พบข้อมูลกลุ่ม</div>
    </div>
  );

  const hasRoles = (detail.roles||[]).length > 0;

  return (
    <div className="fade-in">
      <PageHead title={"กลุ่มของฉัน — " + detail.name} sub="รายชื่อสมาชิก — หัวหน้ากลุ่มสามารถมอบบทบาทให้สมาชิกได้"/>
      <div className="card">
        <div className="card-h">
          <h3>สมาชิก <span className="badge" style={{marginLeft:6}}>{(detail.members||[]).length} คน</span></h3>
        </div>
        <div>
          {(detail.members||[]).length === 0 && (
            <div className="faint tiny" style={{padding:"20px 16px",textAlign:"center"}}>ยังไม่มีสมาชิก</div>
          )}
          {(detail.members||[]).map((m,i) => (
            <div key={m.id} style={{display:"flex",alignItems:"center",gap:12,padding:"12px 16px",borderBottom:i<detail.members.length-1?"1px solid var(--line)":"none"}}>
              <span className="avatar avatar-sm">{m.init || m.display_name[0]}</span>
              <div style={{flex:1}}>
                <div style={{fontWeight:500,fontSize:14}}>{m.display_name}</div>
                <div className="tiny faint">{m.username}{m.job_title ? " · " + m.job_title : ""}</div>
              </div>
              {detail.leader_id === m.id && <span className="badge badge-maroon" style={{flexShrink:0}}>หัวหน้า</span>}
              {hasRoles && detail.leader_id !== m.id && (
                <select
                  className="select" style={{width:180,fontSize:13}}
                  value={m.role||""}
                  disabled={!!saving[m.id]}
                  onChange={e => handleRoleChange(m, e.target.value)}
                >
                  <option value="">ไม่กำหนดบทบาท</option>
                  {(detail.roles||[]).map(r => (
                    <option key={r} value={r}>{DEFAULT_ROLE_LABELS[r]||r}</option>
                  ))}
                </select>
              )}
              {!hasRoles && (
                <span className="tiny faint">{DEFAULT_ROLE_LABELS[m.role]||m.role||'ไม่กำหนดบทบาท'}</span>
              )}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { GroupsPage, MyGroupPage });
