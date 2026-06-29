/* ============================================================
   admin-groups.jsx — จัดการกลุ่ม (admin)
   ============================================================ */

function GroupFormModal({ group, onSave, onClose }) {
  const [name, setName] = useState(group ? group.name : "");
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState("");

  async function submit(e) {
    e.preventDefault();
    if (!name.trim()) { setErr("กรุณาระบุชื่อกลุ่ม"); return; }
    setSaving(true); setErr("");
    try {
      const result = group
        ? await api.updateGroup(group.id, { name: name.trim() })
        : await api.createGroup({ name: name.trim() });
      onSave(result);
    } catch(e) { setErr(e.message); setSaving(false); }
  }

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal" style={{maxWidth:400}} onClick={e=>e.stopPropagation()}>
        <div className="modal-head">
          <h3>{group ? "แก้ไขชื่อกลุ่ม" : "เพิ่มกลุ่มใหม่"}</h3>
          <button className="btn-close" onClick={onClose}><Icon name="x"/></button>
        </div>
        <form onSubmit={submit} style={{padding:"20px 24px",display:"flex",flexDirection:"column",gap:14}}>
          <div>
            <label className="label">ชื่อกลุ่ม</label>
            <input className="input" value={name} onChange={e=>setName(e.target.value)} autoFocus placeholder="เช่น กลุ่มงานวินัย"/>
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
  const [search, setSearch] = useState("");
  const memberIds = new Set(members.map(m => m.id));
  const available = allUsers.filter(u =>
    !memberIds.has(u.id) &&
    (u.display_name.includes(search) || u.username.includes(search) || (u.job_title||"").includes(search))
  );

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal" style={{maxWidth:440}} onClick={e=>e.stopPropagation()}>
        <div className="modal-head">
          <h3>เพิ่มสมาชิกเข้ากลุ่ม <span className="muted">— {group.name}</span></h3>
          <button className="btn-close" onClick={onClose}><Icon name="x"/></button>
        </div>
        <div style={{padding:"16px 24px 0"}}>
          <input className="input" placeholder="ค้นหาชื่อ / username…" value={search} onChange={e=>setSearch(e.target.value)} autoFocus/>
        </div>
        <div style={{maxHeight:320,overflowY:"auto",padding:"8px 24px 20px"}}>
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
              <button className="btn btn-sm btn-outline" onClick={()=>onAdd(u)}>+ เพิ่ม</button>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function GroupsPage({ currentUser }) {
  const [groups, setGroups]       = useState([]);
  const [allUsers, setAllUsers]   = useState([]);
  const [selId, setSelId]         = useState(null);
  const [detail, setDetail]       = useState(null); // {id,name,leader_id,leader_name,members}
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [loading, setLoading]     = useState(true);
  const [showForm, setShowForm]   = useState(false);
  const [editGroup, setEditGroup] = useState(null);
  const [showAddMember, setShowAddMember] = useState(false);
  const [delConfirm, setDelConfirm] = useState(null);

  // โหลดรายการกลุ่มและผู้ใช้ทั้งหมด
  useEffect(() => {
    Promise.all([api.getGroups(), apiFetch('/api/users.php')])
      .then(([g, u]) => { setGroups(g); setAllUsers(u); })
      .catch(e => alert(e.message))
      .finally(() => setLoading(false));
  }, []);

  // โหลด detail เมื่อเลือกกลุ่ม
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
    if (grp.id === selId) setDetail(d => d ? {...d, name: grp.name} : d);
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
      setDetail(d => ({...d, leader_id: updated.leader_id, leader_name: updated.leader_name}));
      setGroups(gs => gs.map(g => g.id === detail.id ? {...g, leader_id: updated.leader_id, leader_name: updated.leader_name} : g));
    } catch(e) { alert(e.message); }
  }

  async function handleAddMember(user) {
    if (!detail) return;
    try {
      await api.addGroupMember(detail.id, user.id);
      setDetail(d => ({...d, members: [...(d.members||[]), user]}));
      setGroups(gs => gs.map(g => g.id === detail.id ? {...g, member_count: (g.member_count||0)+1} : g));
      setAllUsers(us => us.map(u => u.id === user.id ? {...u, group_name: detail.name} : u));
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
        leader_id: d.leader_id === member.id ? null : d.leader_id,
        leader_name: d.leader_id === member.id ? null : d.leader_name,
      }));
      setGroups(gs => gs.map(g => g.id === detail.id ? {...g, member_count: Math.max(0,(g.member_count||1)-1)} : g));
      setAllUsers(us => us.map(u => u.id === member.id ? {...u, group_name: null} : u));
    } catch(e) { alert(e.message); }
  }

  if (loading) return <LoadingSpinner/>;

  const selGroup = groups.find(g => g.id === selId);

  return (
    <div className="fade-in">
      <PageHead title="จัดการกลุ่ม" sub="เพิ่ม ลบ แก้ไขกลุ่ม จัดการสมาชิก และแต่งตั้งหัวหน้ากลุ่ม">
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
              <div key={g.id}
                onClick={() => setSelId(g.id)}
                style={{
                  display:"flex", alignItems:"center", gap:10,
                  padding:"12px 16px", cursor:"pointer",
                  background: selId===g.id ? "var(--maroon-50)" : "transparent",
                  borderBottom:"1px solid var(--line)",
                  transition:"background .12s",
                }}>
                <div style={{flex:1,minWidth:0}}>
                  <div style={{fontWeight:selId===g.id?600:500,fontSize:14,color:selId===g.id?"var(--accent)":"var(--ink)"}}>{g.name}</div>
                  <div className="tiny faint" style={{marginTop:2}}>
                    {g.member_count} คน{g.leader_name ? ` · หัวหน้า: ${g.leader_name}` : ""}
                  </div>
                </div>
                <div style={{display:"flex",gap:4,flexShrink:0}} onClick={e=>e.stopPropagation()}>
                  <button className="btn btn-ghost btn-sm" title="แก้ไขชื่อ"
                    onClick={()=>{ setEditGroup(g); setShowForm(true); }}>
                    <Icon name="edit" style={{width:14,height:14}}/>
                  </button>
                  <button className="btn btn-ghost btn-sm" title="ลบกลุ่ม"
                    style={{color:"var(--sla-r)"}}
                    onClick={()=>setDelConfirm(g)}>
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
                {/* หัวหน้ากลุ่ม */}
                <div className="card card-pad">
                  <h3 style={{fontSize:15,marginBottom:14}}>หัวหน้ากลุ่ม</h3>
                  {detail.leader_id ? (
                    <div className="between">
                      <div className="vcenter" style={{gap:10}}>
                        <span className="avatar">{detail.leader_init || (detail.leader_name||"?")[0]}</span>
                        <div>
                          <div style={{fontWeight:600}}>{detail.leader_name}</div>
                          <div className="tiny faint">หัวหน้ากลุ่ม {detail.name}</div>
                        </div>
                      </div>
                      <button className="btn btn-outline btn-sm" onClick={()=>handleSetLeader(detail.leader_id)}>
                        ถอดหัวหน้า
                      </button>
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
                          ? <span className="badge badge-maroon">หัวหน้า</span>
                          : <button className="btn btn-ghost btn-sm" style={{fontSize:11}} onClick={()=>handleSetLeader(m.id)}>แต่งตั้งหัวหน้า</button>
                        }
                        <button className="btn btn-ghost btn-sm" style={{color:"var(--sla-r)"}}
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
        <GroupFormModal
          group={editGroup}
          onSave={handleGroupSaved}
          onClose={()=>{ setShowForm(false); setEditGroup(null); }}
        />
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

/* ── MyGroupPage — หัวหน้ากลุ่มดูสมาชิก ── */
function MyGroupPage({ currentUser }) {
  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!currentUser?.leader_of_group) { setLoading(false); return; }
    api.getGroupMembers(currentUser.leader_of_group.id)
      .then(setDetail)
      .catch(e => alert(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <LoadingSpinner/>;
  if (!detail) return (
    <div className="card card-pad fade-in">
      <div className="faint" style={{textAlign:"center",padding:32}}>ไม่พบข้อมูลกลุ่ม</div>
    </div>
  );

  return (
    <div className="fade-in">
      <PageHead title={"กลุ่มของฉัน — " + detail.name} sub="รายชื่อสมาชิกในกลุ่มที่ท่านเป็นหัวหน้า"/>
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
                <div className="tiny faint">{m.username} · {DEFAULT_ROLE_LABELS[m.role]||m.role} {m.job_title ? "· " + m.job_title : ""}</div>
              </div>
              {detail.leader_id === m.id && <span className="badge badge-maroon">หัวหน้า</span>}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { GroupsPage, MyGroupPage });
