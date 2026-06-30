/* ============================================================
   app.jsx — Login, โครงแอดมิน, theme, mount
   ============================================================ */

/* ---------------- Theme ---------------- */
function useTheme() {
  const [theme, setTheme] = useState(() => localStorage.getItem("ovec-theme") || "system");
  useEffect(() => {
    localStorage.setItem("ovec-theme", theme);
    const mq = window.matchMedia("(prefers-color-scheme: dark)");
    const apply = () => {
      const resolved = theme === "system" ? (mq.matches ? "dark" : "light") : theme;
      document.documentElement.setAttribute("data-theme", resolved);
    };
    apply();
    mq.addEventListener("change", apply);
    return () => mq.removeEventListener("change", apply);
  }, [theme]);
  return [theme, setTheme];
}

/* ---------------- Login ---------------- */
function AdminLogin({ go, onLogin }) {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [showPass, setShowPass] = useState(false);
  const [loading,  setLoading]  = useState(false);
  const [error,    setError]    = useState("");

  const doLogin = async (e) => {
    e && e.preventDefault();
    setLoading(true); setError("");
    try {
      const user = await api.login(username, password);
      onLogin(user);
    } catch(err) {
      setError(err.message || "เข้าสู่ระบบไม่สำเร็จ");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{minHeight:"100vh",display:"grid",gridTemplateColumns:"1fr 1fr"}}>
      <div data-on-dark style={{background:"linear-gradient(160deg,var(--maroon-700),var(--maroon) 60%,var(--maroon-600))",color:"#fff",padding:"56px 60px",display:"flex",flexDirection:"column",justifyContent:"space-between",position:"relative",overflow:"hidden"}}>
        <img src="assets/ovec-logo.svg" style={{position:"absolute",right:-80,bottom:-80,width:380,opacity:.08,filter:"brightness(3) saturate(0)"}} alt=""/>
        <div className="vcenter" style={{gap:13,position:"relative"}}>
          <img src="assets/ovec-logo.svg" style={{width:52,height:52}} alt=""/>
          <div><div style={{fontWeight:700,fontSize:15}}>สำนักงานคณะกรรมการการอาชีวศึกษา</div><div style={{fontSize:12,opacity:.85}}>Office of Vocational Education Commission</div></div>
        </div>
        <div style={{position:"relative"}}>
          <h1 style={{fontSize:32,fontWeight:700,letterSpacing:"-.02em"}}>ระบบบริหารงานนิติการ</h1>
          <p style={{opacity:.9,marginTop:14,lineHeight:1.7,maxWidth:380}}>สำหรับเจ้าหน้าที่กลุ่มนิติการและผู้บริหาร ในการรับเรื่อง คัดกรอง สอบสวน และติดตามผลการดำเนินงานอย่างปลอดภัยและตรวจสอบได้</p>
          <div className="vcenter" style={{gap:18,marginTop:26,flexWrap:"wrap"}}>
            <span className="vcenter tiny" style={{gap:7,opacity:.9}}><Icon name="shieldCheck" style={{width:16,height:16}}/> RBAC + Audit</span>
            <span className="vcenter tiny" style={{gap:7,opacity:.9}}><Icon name="lock" style={{width:16,height:16}}/> Session + bcrypt</span>
            <span className="vcenter tiny" style={{gap:7,opacity:.9}}><Icon name="clock" style={{width:16,height:16}}/> ติดตาม SLA</span>
          </div>
        </div>
        <div className="tiny" style={{opacity:.7,position:"relative"}}>© {(new Date().getFullYear()+543)} สอศ. · PHP 8 + MariaDB 10</div>
      </div>

      <div style={{display:"grid",placeItems:"center",padding:30}}>
        <form style={{width:"100%",maxWidth:380}} onSubmit={doLogin}>
          <h2 style={{fontSize:24}}>เข้าสู่ระบบเจ้าหน้าที่</h2>
          <p className="muted" style={{marginTop:6,marginBottom:26}}>ยืนยันตัวตนด้วยบัญชีองค์กร</p>
          {error && <div className="notice notice-warn" style={{marginBottom:16}}><Icon name="alert"/><div>{error}</div></div>}
          <div className="grid" style={{gap:14}}>
            <div className="field">
              <label>ชื่อผู้ใช้</label>
              <input className="input" value={username} onChange={e=>setUsername(e.target.value.replace(/[^a-zA-Z0-9._-]/g, ""))} autoComplete="username" inputMode="email" lang="en"/>
            </div>
            <div className="field">
              <label>รหัสผ่าน</label>
              <div style={{position:"relative"}}>
                <input className="input" style={{paddingRight:40}} type={showPass ? "text" : "password"} value={password} onChange={e=>setPassword(e.target.value)} autoComplete="current-password"/>
                <button type="button" onClick={()=>setShowPass(v=>!v)} aria-label={showPass ? "ซ่อนรหัสผ่าน" : "แสดงรหัสผ่าน"}
                  style={{position:"absolute",right:10,top:"50%",transform:"translateY(-50%)",background:"none",border:"none",cursor:"pointer",padding:4,display:"flex",color:"var(--ink-3)"}}>
                  <Icon name={showPass ? "eyeOff" : "eye"} style={{width:18,height:18}}/>
                </button>
              </div>
            </div>
            <button type="submit" className="btn btn-primary btn-lg btn-block" disabled={loading}>
              {loading ? <LoadingSpinner/> : <><Icon name="lock" style={{width:16,height:16}}/> เข้าสู่ระบบ</>}
            </button>
          </div>
          <button type="button" className="btn btn-ghost btn-block" style={{marginTop:16}} onClick={()=>go("home")}><Icon name="chevL" style={{width:16,height:16}}/> กลับหน้าประชาชน</button>
        </form>
      </div>
    </div>
  );
}

/* ---------------- Admin shell ---------------- */

function canManageUsers(user) {
  return user?.role === 'admin' || user?.role === 'dir_legal' || !!user?.can_manage_users;
}

function navFor(role, counts, user) {
  if (role === "admin") return [
    {v:"dashboard", ic:"pie",         l:"ภาพรวม"},
    {v:"cases",     ic:"inbox",       l:"หนังสือเข้าทั้งหมด"},
    {v:"calendar",  ic:"calendar",    l:"ปฏิทินการดำเนินงาน"},
    {v:"reports",   ic:"chart",       l:"รายงาน"},
    {sec:"ระบบ"},
    {v:"users",        ic:"users",       l:"จัดการผู้ใช้"},
    {v:"officers-mgt", ic:"gavel",      l:"จัดการบุคลากร"},
    {v:"groups",       ic:"users",      l:"จัดการกลุ่ม"},
    {v:"lookup",       ic:"filter",     l:"รายการอ้างอิง"},
    {v:"roles",        ic:"flag",       l:"ชื่อบทบาท"},
    {v:"todos",        ic:"checkCircle",l:"รายการที่ต้องทำ"},
    {v:"sla",          ic:"settings",   l:"ตั้งค่า SLA"},
    {v:"system",       ic:"settings",   l:"ตั้งค่าระบบ"},
  ];
  if (role === "officer") return [
    {v:"dashboard", ic:"home",     l:"แดชบอร์ด"},
    {sec:"การดำเนินงาน"},
    {v:"cases",     ic:"inbox",    l:"จัดการเรื่อง", count:counts.newQ},
    {v:"vault",     ic:"layers",   l:"คลังสำนวน & ไฟล์"},
    {v:"calendar",  ic:"calendar", l:"ปฏิทินการดำเนินงาน"},
    {v:"reports",   ic:"chart",    l:"รายงาน"},
    ...(canManageUsers(user) ? [{sec:"ระบบ"},{v:"users",ic:"users",l:"จัดการผู้ใช้"}] : []),
    ...(user?.leader_of_group ? [{sec:"กลุ่ม"},{v:"my-group",ic:"users",l:"กลุ่มของฉัน"}] : []),
  ];
  if (role === "clerk") return [
    {v:"dashboard", ic:"home",     l:"แดชบอร์ด"},
    {sec:"การดำเนินงาน"},
    {v:"cases",     ic:"inbox",    l:"งานที่ได้รับมอบหมาย", count:counts.newQ},
    {v:"vault",     ic:"layers",   l:"คลังสำนวน & ไฟล์"},
    {v:"calendar",  ic:"calendar", l:"ปฏิทินการดำเนินงาน"},
    ...(user?.leader_of_group ? [{sec:"กลุ่ม"},{v:"my-group",ic:"users",l:"กลุ่มของฉัน"}] : []),
  ];
  if (role === "head_secretary") return [
    {v:"dashboard", ic:"home",     l:"แดชบอร์ด"},
    {sec:"การดำเนินงาน"},
    {v:"cases",     ic:"inbox",    l:"เรื่องรอเกษียน", count:counts.newQ},
    {v:"import",    ic:"filePlus", l:"นำเข้าเรื่องจากเอกสาร"},
    {v:"vault",     ic:"layers",   l:"คลังสำนวน & ไฟล์"},
    {v:"calendar",  ic:"calendar", l:"ปฏิทินการดำเนินงาน"},
    ...(user?.leader_of_group ? [{sec:"กลุ่ม"},{v:"my-group",ic:"users",l:"กลุ่มของฉัน"}] : []),
  ];
  if (role === "dir_legal") return [
    {v:"exec",      ic:"pie",      l:"Dashboard ผู้บริหาร"},
    {v:"dashboard", ic:"gavel",    l:"ภาพรวมกลุ่ม"},
    {sec:"การดำเนินงาน"},
    {v:"proposals", ic:"flag",     l:"เรื่องที่ได้รับมอบหมาย", count:counts.pendingProposals},
    {v:"calendar",  ic:"calendar", l:"ปฏิทินการดำเนินงาน"},
    {v:"reports",   ic:"chart",    l:"รายงานกลุ่ม"},
    {v:"sla",       ic:"settings", l:"ตั้งค่า SLA"},
    {sec:"ระบบ"},
    {v:"users",     ic:"users",    l:"จัดการผู้ใช้"},
  ];
  // deputy_secretary, secretary
  if (role !== "dir_admin") return [
    {v:"exec",      ic:"pie",      l:"Dashboard ผู้บริหาร"},
    {sec:"การดำเนินงาน"},
    {v:"cases",     ic:"inbox",    l:"หนังสือเข้าทั้งหมด"},
    {v:"calendar",  ic:"calendar", l:"ปฏิทินการดำเนินงาน"},
    {v:"reports",   ic:"chart",    l:"รายงานผู้บริหาร"},
  ];
  // dir_admin
  return [
    {v:"exec",      ic:"pie",      l:"Dashboard ผู้บริหาร"},
    {sec:"การดำเนินงาน"},
    {v:"cases",     ic:"inbox",    l:"หนังสือเข้าทั้งหมด"},
    {v:"proposals", ic:"flag",     l:"เกษียนรอพิจารณา", count:counts.pendingProposals},
    {v:"calendar",  ic:"calendar", l:"ปฏิทินการดำเนินงาน"},
    {v:"reports",   ic:"chart",    l:"รายงานผู้บริหาร"},
  ];
}

/* ---------------- Avatar (ภาพ หรือ ตัวย่อ) ---------------- */
function Avatar({ user, size = "md", style }) {
  const url = avatarUrl(user);
  const cls = "avatar" + (size === "sm" ? " avatar-sm" : "");
  if (url) {
    return <img src={url} alt="" className={cls} style={{objectFit:"cover", ...style}}/>;
  }
  return <span className={cls} style={style}>{user.init}</span>;
}

/* ---------------- Profile modal ---------------- */
function ProfileModal({ user, onSave, onClose }) {
  const [displayName, setDisplayName] = useState(user.display_name || "");
  const [init, setInit] = useState(user.init || "");
  const [curPass, setCurPass] = useState("");
  const [newPass, setNewPass] = useState("");
  const [showPass, setShowPass] = useState(false);
  const [saving, setSaving] = useState(false);
  const [avatarBusy, setAvatarBusy] = useState(false);
  const [liveUser, setLiveUser] = useState(user);
  const [err, setErr] = useState("");
  const fileRef = useRef(null);

  const pickAvatar = async (e) => {
    const file = e.target.files?.[0];
    e.target.value = "";
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { setErr("ไฟล์ภาพขนาดใหญ่เกิน 2 MB"); return; }
    setAvatarBusy(true); setErr("");
    try {
      const saved = await api.uploadAvatar(file);
      setLiveUser(u => ({ ...u, ...saved }));
      onSave(saved, { silent: true });
    } catch (e) {
      setErr(e.message);
    }
    setAvatarBusy(false);
  };

  const removeAvatar = async () => {
    setAvatarBusy(true); setErr("");
    try {
      const saved = await api.removeAvatar();
      setLiveUser(u => ({ ...u, ...saved }));
      onSave(saved, { silent: true });
    } catch (e) {
      setErr(e.message);
    }
    setAvatarBusy(false);
  };

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true); setErr("");
    try {
      const patch = { display_name: displayName, init };
      if (newPass) {
        if (newPass.length < 6) { setErr("รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร"); setSaving(false); return; }
        patch.current_password = curPass;
        patch.new_password = newPass;
      }
      const saved = await api.updateProfile(patch);
      onSave(saved);
    } catch (e) {
      setErr(e.message);
    }
    setSaving(false);
  };

  return (
    <div style={{position:"fixed",inset:0,background:"rgba(20,10,12,.55)",display:"flex",alignItems:"center",justifyContent:"center",zIndex:200,padding:24}} onClick={onClose}>
      <div style={{background:"var(--surface)",borderRadius:12,boxShadow:"0 8px 40px rgba(0,0,0,.35)",width:"100%",maxWidth:440,maxHeight:"90vh",display:"flex",flexDirection:"column"}} onClick={e=>e.stopPropagation()}>
        <div style={{padding:"20px 24px",borderBottom:"1px solid var(--line)",display:"flex",alignItems:"center",justifyContent:"space-between",flexShrink:0}}>
          <h3 style={{margin:0,fontSize:17}}>แก้ไขโปรไฟล์</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <form id="profile-form" onSubmit={submit} style={{padding:"0 24px",display:"flex",flexDirection:"column",gap:14,overflowY:"auto",flex:1}}>
          {err && <div className="notice notice-err" style={{marginTop:16}}><Icon name="alert"/><div>{err}</div></div>}

          <div className="vcenter" style={{gap:16,marginTop:16}}>
            <div style={{position:"relative",width:64,height:64,flexShrink:0}}>
              <Avatar user={liveUser} size="lg" style={{width:64,height:64,fontSize:20}}/>
              {avatarBusy && <div style={{position:"absolute",inset:0,display:"grid",placeItems:"center",background:"rgba(0,0,0,.35)",borderRadius:"50%"}}><LoadingSpinner/></div>}
            </div>
            <div style={{display:"flex",flexDirection:"column",gap:6}}>
              <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" style={{display:"none"}} onChange={pickAvatar}/>
              <button type="button" className="btn btn-outline btn-sm" onClick={()=>fileRef.current?.click()} disabled={avatarBusy}>
                <Icon name="paperclip" style={{width:14,height:14}}/> เปลี่ยนภาพ
              </button>
              {liveUser.avatar_path && (
                <button type="button" className="btn btn-ghost btn-sm" onClick={removeAvatar} disabled={avatarBusy}>
                  <Icon name="x" style={{width:14,height:14}}/> ลบภาพ
                </button>
              )}
              <span className="hint">JPG, PNG, WEBP ไม่เกิน 2 MB</span>
            </div>
          </div>

          <div className="field" style={{marginTop:4}}>
            <label>ชื่อแสดง <span className="req">*</span></label>
            <input className="input" value={displayName} onChange={e=>setDisplayName(e.target.value)} required/>
          </div>
          <div className="field">
            <label>ตัวย่อ</label>
            <input className="input" value={init} onChange={e=>setInit(e.target.value)} maxLength={5} placeholder="เช่น วว"/>
          </div>

          <hr className="divider" style={{border:"none",borderTop:"1px solid var(--line)",margin:"6px 0"}}/>
          <p className="sm muted" style={{margin:0}}>เปลี่ยนรหัสผ่าน (เว้นว่างถ้าไม่ต้องการเปลี่ยน)</p>

          <div className="field">
            <label>รหัสผ่านปัจจุบัน</label>
            <input className="input" type="password" value={curPass} onChange={e=>setCurPass(e.target.value)} autoComplete="current-password"/>
          </div>
          <div className="field">
            <label>รหัสผ่านใหม่</label>
            <div style={{position:"relative"}}>
              <input className="input" style={{paddingRight:40}} type={showPass ? "text" : "password"} value={newPass} onChange={e=>setNewPass(e.target.value)} autoComplete="new-password" minLength={6}/>
              <button type="button" onClick={()=>setShowPass(v=>!v)} aria-label={showPass ? "ซ่อนรหัสผ่าน" : "แสดงรหัสผ่าน"}
                style={{position:"absolute",right:10,top:"50%",transform:"translateY(-50%)",background:"none",border:"none",cursor:"pointer",padding:4,display:"flex",color:"var(--ink-3)"}}>
                <Icon name={showPass ? "eyeOff" : "eye"} style={{width:18,height:18}}/>
              </button>
            </div>
            <span className="hint">อย่างน้อย 6 ตัวอักษร</span>
          </div>

        </form>
        <div className="modal-f">
          <button type="button" className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
          <button type="submit" form="profile-form" className="btn btn-primary" disabled={saving}>
            {saving ? <LoadingSpinner/> : "บันทึก"}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ---------------- User menu (dropdown) ---------------- */
function UserMenu({ user, role, roleLabels, onEditProfile, onLogout, onSwitchRole, size = "md" }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

  useEffect(() => {
    if (!open) return;
    const close = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, [open]);

  const isSm = size === "sm";

  return (
    <div ref={ref} style={{position:"relative"}}>
      <button type="button" onClick={()=>setOpen(v=>!v)}
        className="vcenter" style={{gap:isSm?9:10,padding:isSm?"0 6px":"6px 8px",background:"none",border:"none",cursor:"pointer",width:"100%",textAlign:"left",borderRadius:8,color:"inherit"}}>
        <Avatar user={user} size={isSm ? "sm" : "md"}/>
        {isSm ? (
          <div style={{lineHeight:1.2}}>
            <div className="sm" style={{fontWeight:600}}>{(user.display_name||"").split(" ")[0]}</div>
            <div className="faint tiny">{roleLabel(role, roleLabels)}</div>
          </div>
        ) : (
          <div style={{flex:1,minWidth:0}}>
            <div className="sm" style={{fontWeight:600,whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis"}}>{user.display_name}</div>
            <div className="faint tiny">{roleLabel(role, roleLabels)}</div>
          </div>
        )}
        <Icon name="chevD" style={{width:14,height:14,flexShrink:0,opacity:.6}}/>
      </button>
      {open && (
        <div className="card" style={{position:"absolute",bottom:isSm?"auto":"calc(100% + 6px)",top:isSm?"calc(100% + 6px)":"auto",right:0,minWidth:200,padding:6,zIndex:100,boxShadow:"0 8px 30px rgba(0,0,0,.18)"}}>
          {Array.isArray(user.roles) && user.roles.length > 1 && (
            <>
              <div className="faint tiny" style={{padding:"6px 10px 4px",textTransform:"uppercase",letterSpacing:".04em"}}>ใช้งานในบทบาท</div>
              {(() => {
                // map บทบาทหัวหน้ากลุ่ม → ชื่อกลุ่ม เพื่อต่อท้ายชื่อตำแหน่ง
                const lgMap = {};
                (user.leader_groups || []).forEach(g => { if (g.leader_role) lgMap[g.leader_role] = g.name; });
                return user.roles.map(r => (
                  <button key={r} className="nav-item" onClick={()=>{ setOpen(false); if (r!==role && onSwitchRole) onSwitchRole(r); }}
                    style={{fontWeight: r===role?700:400}}>
                    <Icon name={r===role?"check":"shield"} style={{width:16,height:16,opacity:r===role?1:.5}}/>
                    {roleLabel(r, roleLabels)}{lgMap[r] ? ' ' + lgMap[r] : ''}
                  </button>
                ));
              })()}
              <div style={{borderTop:"1px solid var(--line)",margin:"4px 0"}}/>
            </>
          )}
          <button className="nav-item" onClick={()=>{ setOpen(false); onEditProfile(); }}>
            <Icon name="edit" style={{width:16,height:16}}/> แก้ไขโปรไฟล์
          </button>
          <button className="nav-item" onClick={()=>{ setOpen(false); onLogout(); }}>
            <Icon name="logout" style={{width:16,height:16}}/> ออกจากระบบ
          </button>
        </div>
      )}
    </div>
  );
}

/* ══════════════════════════════════════════════════════════
   NotificationBell — กระดิ่งแจ้งเตือน (poll 60s)
══════════════════════════════════════════════════════════ */
const NOTIF_TYPE_LABEL = {
  assigned:'ได้รับมอบหมายงาน',
  pre_14:'ใกล้ครบกำหนด (14 วัน)', pre_7:'ใกล้ครบกำหนด (7 วัน)',
  pre_3:'ใกล้ครบกำหนด (3 วัน)',   pre_1:'ใกล้ครบกำหนด (1 วัน)',
  over_1:'เกินกำหนด 1 วัน',       over_3:'เกินกำหนด 3 วัน',
  over_7:'เกินกำหนด 7 วัน',       over_weekly:'แจ้งเตือนซ้ำรายสัปดาห์',
  escalate_7:'Escalate ≥7 วัน',   escalate_15:'Escalate ≥15 วัน',
  escalate_30:'Escalate ≥30 วัน',
};

function NotificationBell({ onOpenCase }) {
  const [items,  setItems]  = useState([]);
  const [unread, setUnread] = useState(0);
  const [open,   setOpen]   = useState(false);
  const ref = useRef(null);

  const load = useCallback(() => {
    api.getNotifications(50)
       .then(r => { setItems(r.items || []); setUnread(r.unread || 0); })
       .catch(()=>{});
  }, []);

  useEffect(() => {
    load();
    const t = setInterval(load, 60000);
    return () => clearInterval(t);
  }, [load]);

  useEffect(() => {
    if (!open) return;
    const close = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', close);
    return () => document.removeEventListener('mousedown', close);
  }, [open]);

  const markRead = (item) => {
    if (!item.read_at) {
      api.markNotifRead(item.id).then(() => {
        setItems(ns => ns.map(n => n.id === item.id ? {...n, read_at: new Date().toISOString()} : n));
        setUnread(u => Math.max(0, u - 1));
      }).catch(()=>{});
    }
    if (item.case_id && onOpenCase) { setOpen(false); onOpenCase(item.case_id); }
  };

  const markAll = () => {
    api.markAllNotifsRead().then(() => {
      setItems(ns => ns.map(n => ({...n, read_at: n.read_at || new Date().toISOString()})));
      setUnread(0);
    }).catch(()=>{});
  };

  const timeAgo = (iso) => {
    if (!iso) return '';
    const d = Math.floor((Date.now() - new Date(iso)) / 60000);
    if (d < 1)   return 'เมื่อกี้';
    if (d < 60)  return `${d} นาทีที่แล้ว`;
    if (d < 1440) return `${Math.floor(d/60)} ชั่วโมงที่แล้ว`;
    return `${Math.floor(d/1440)} วันที่แล้ว`;
  };

  const notifColor = (type) => {
    if (type === 'assigned')           return 'var(--ok)';
    if (type?.startsWith('pre'))       return 'var(--warn)';
    if (type?.startsWith('escalate'))  return '#9333ea';
    return 'var(--danger)';
  };

  return (
    <div ref={ref} style={{position:'relative'}}>
      <button onClick={()=>setOpen(v=>!v)}
        style={{position:'relative',background:'none',border:'none',cursor:'pointer',
          padding:'6px 8px',borderRadius:8,color:'inherit',display:'flex',alignItems:'center'}}>
        <Icon name="bell" style={{width:20,height:20,color:unread>0?'var(--danger)':'var(--ink-2)'}}/>
        {unread > 0 && (
          <span style={{position:'absolute',top:2,right:2,
            background:'var(--danger)',color:'#fff',borderRadius:'50%',
            fontSize:10,fontWeight:700,minWidth:16,height:16,
            display:'flex',alignItems:'center',justifyContent:'center',padding:'0 3px',
            lineHeight:1,border:'2px solid var(--surface)'}}>
            {unread > 99 ? '99+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="card" style={{
          position:'absolute',top:'calc(100% + 8px)',right:0,
          width:'min(400px,95vw)',maxHeight:'min(520px,80vh)',
          display:'flex',flexDirection:'column',
          boxShadow:'0 12px 40px rgba(0,0,0,.18)',zIndex:500,padding:0,overflow:'hidden',
        }}>
          {/* header */}
          <div style={{padding:'12px 16px',borderBottom:'1px solid var(--line)',
            display:'flex',alignItems:'center',justifyContent:'space-between',flexShrink:0}}>
            <div style={{fontWeight:700,fontSize:14}}>
              การแจ้งเตือน
              {unread > 0 && <span style={{marginLeft:8,background:'var(--danger)',
                color:'#fff',borderRadius:12,fontSize:11,fontWeight:700,padding:'1px 7px'}}>
                {unread}
              </span>}
            </div>
            {unread > 0 && (
              <button onClick={markAll}
                style={{fontSize:11,color:'var(--maroon)',background:'none',border:'none',
                  cursor:'pointer',padding:'2px 6px',borderRadius:6,fontWeight:600}}>
                อ่านทั้งหมด
              </button>
            )}
          </div>

          {/* list */}
          <div style={{overflowY:'auto',flex:1}}>
            {items.length === 0 ? (
              <div style={{padding:32,textAlign:'center',color:'var(--ink-3)',fontSize:13}}>
                ไม่มีการแจ้งเตือน
              </div>
            ) : items.map(n => (
              <button key={n.id} onClick={()=>markRead(n)}
                style={{display:'block',width:'100%',textAlign:'left',
                  background: n.read_at ? 'none' : 'rgba(107,29,42,.04)',
                  border:'none',borderBottom:'1px solid var(--line)',
                  padding:'10px 16px',cursor:'pointer',transition:'background .15s'}}
                onMouseEnter={e=>e.currentTarget.style.background='var(--surface-2)'}
                onMouseLeave={e=>e.currentTarget.style.background=n.read_at?'none':'rgba(107,29,42,.04)'}>
                <div style={{display:'flex',gap:10,alignItems:'flex-start'}}>
                  <div style={{width:8,height:8,borderRadius:'50%',flexShrink:0,marginTop:5,
                    background: n.read_at ? 'var(--ink-4)' : notifColor(n.notif_type)}}/>
                  <div style={{flex:1,minWidth:0}}>
                    <div style={{fontSize:12,fontWeight: n.read_at ? 400 : 600,
                      color: n.read_at ? 'var(--ink-2)' : 'var(--ink)',
                      lineHeight:1.4,marginBottom:2}}>
                      {n.title}
                    </div>
                    {n.body && <div style={{fontSize:11,color:'var(--ink-3)',lineHeight:1.4,marginBottom:3}}>
                      {n.body}
                    </div>}
                    <div style={{fontSize:10,color:'var(--ink-4)',display:'flex',gap:8}}>
                      <span style={{background:'var(--surface-2)',borderRadius:4,padding:'1px 5px'}}>
                        {NOTIF_TYPE_LABEL[n.notif_type] || n.notif_type}
                      </span>
                      <span>{timeAgo(n.created_at)}</span>
                    </div>
                  </div>
                </div>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function AdminApp({ user, setUser, go, theme, setTheme, onLogout }) {
  const role = user.role;
  const execRoles = ['dir_legal','dir_admin','deputy_secretary','secretary'];
  const [view, setView] = useState(execRoles.includes(role) ? "exec" : "dashboard");
  const [sel,  setSel]              = useState(null);
  const [cases, setCases]           = useState([]);
  const [officers, setOfficers]     = useState([]);
  const [loading, setLoading]       = useState(true);
  const [roleLabels, setRoleLabels] = useState(window.__ROLE_LABELS__ || {});
  const [pendingProposals, setPendingProposals] = useState([]);

  useEffect(() => {
    Promise.all([api.getCases(), api.getOfficers()])
      .then(([c, o]) => { setCases(c); setOfficers(o); })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (role === 'dir_legal' || role === 'dir_admin' || role === 'admin') {
      api.getAssignProposals().then(setPendingProposals).catch(() => {});
    }
  }, [role]);

  const refreshCases = async () => {
    const fresh = await api.getCases().catch(() => cases);
    setCases(fresh);
  };

  const counts = {
    newQ: role === 'head_secretary'
      ? cases.filter(c => !c.assignee && c.status === 'received').length
      : cases.filter(c=>["received","screening"].includes(c.status)).length,
    pendingProposals: role === 'dir_legal'
      ? cases.filter(c => {
          const myGroups = (user?.leader_groups || []).map(g => g.name);
          return (c.group_recv_no && myGroups.includes(c.assigned_group)) ||
                 (user?.officer_id && String(c.lawyer) === String(user.officer_id));
        }).length
      : pendingProposals.length,
  };
  const nav    = navFor(role, counts, user);
  const openCase = (id) => { setSel(id); setView("case-detail"); };
  const updateCase = async (id, patch) => {
    try {
      const updated = await api.updateCase(id, patch);
      setCases(cs => cs.map(c => c.id === id ? { ...c, ...updated } : c));
    } catch(e) {
      alert(e.message);
    }
  };

  const sectionTitle = {
    exec:"Dashboard ผู้บริหาร",
    dashboard:"แดชบอร์ด", cases:"จัดการเรื่อง",
    "case-detail":"รายละเอียดสำนวน", import:"นำเข้าเรื่อง",
    vault:"คลังสำนวน", reports:"รายงาน", users:"จัดการผู้ใช้", proposals:"ข้อเสนอรอพิจารณา",
    todos:"รายการที่ต้องทำ", sla:"ตั้งค่า SLA", roles:"ชื่อบทบาท",
    "officers-mgt":"จัดการบุคลากร", lookup:"รายการอ้างอิง",
    calendar:"ปฏิทินการดำเนินงาน",
  }[view] || "";

  let content;
  if (loading) {
    content = <LoadingSpinner/>;
  } else if (view === "case-detail") {
    content = <CaseDetail cid={sel} cases={cases} officers={officers} back={()=>setView(role==="dir_legal" ? "proposals" : "cases")} updateCase={updateCase} role={role}
      currentUser={user} onCaseDeleted={id=>setCases(cs=>cs.filter(c=>c.id!==id))} onRefresh={refreshCases}/>;
  } else if (view === "exec") {
    content = <ExecDashboard currentUser={user} onOpenCase={openCase}/>;
  } else if (view === "proposals") {
    const canApproveProposals = role === "dir_admin" || role === "admin";
    if (role === "dir_legal") {
      // นิติการ: เห็นเรื่องที่ clerk เกษียนเข้ากลุ่มที่ตนเป็นหัวหน้า + เรื่องที่ถูกมอบหมายเป็นนิติกร
      const myGroups = (user?.leader_groups || []).map(g => g.name);
      const myCases = (cases || []).filter(c =>
        (c.group_recv_no && myGroups.includes(c.assigned_group)) ||
        (user?.officer_id && String(c.lawyer) === String(user.officer_id))
      );
      content = <CaseListPage cases={myCases} officers={officers} openCase={openCase}
        title="เรื่องที่ได้รับมอบหมาย" sub="เรื่องที่เกษียนเข้ากลุ่มของท่าน รอพิจารณามอบหมายนิติกร"/>;
    } else {
      content = <AssignProposalsPage proposals={pendingProposals} officers={officers}
        openCase={openCase}
        canApprove={canApproveProposals}
        title={canApproveProposals ? "ข้อเสนอรอพิจารณา" : "เรื่องที่ได้รับมอบหมาย"}
        sub={canApproveProposals ? "ข้อเสนอมอบหมายสำนวนจากหัวหน้าธุรการ" : "เรื่องที่หัวหน้าธุรการเสนอมายังกลุ่มของท่าน"}
        onApproved={(caseId) => {
          api.getAssignProposals().then(setPendingProposals).catch(()=>{});
          refreshCases();
          openCase(caseId);
        }}/>;
    }
  } else if (view === "dashboard") {
    content = (role === "officer" || role === "clerk")
      ? <OfficerDashboard cases={cases} officers={officers} openCase={openCase} setView={setView}/>
      : role === "head_secretary"
      ? <HeadSecretaryDashboard cases={cases} officers={officers} openCase={openCase} setView={setView}
          onProposed={() => refreshCases()}/>
      : role === "dir_legal"
      ? <DirLegalDashboard cases={cases} officers={officers} openCase={openCase} setView={setView}/>
      : <DirAdminDashboard cases={cases} officers={officers} setView={setView}/>;
  } else if (view === "cases") {
    const caseListTitle = role === "head_secretary" ? "เรื่องรอเกษียน" : role === "clerk" ? "งานที่ได้รับมอบหมาย" : "จัดการเรื่องร้องเรียน–ร้องทุกข์";
    const caseListSub   = role === "head_secretary" ? "สำนวนที่ยังไม่ได้รับมอบหมาย — เลือกเพื่อนำเสนอผู้อำนวยการ" : role === "clerk" ? "งานที่หัวหน้าธุรการมอบหมายให้" : undefined;
    content = <CaseListPage cases={cases} officers={officers} openCase={openCase}
      title={caseListTitle} sub={caseListSub}
      defaultStatus={role === "head_secretary" ? "received" : "all"}/>;
  } else if (view === "import") {
    content = <ImportDocument back={()=>{ setView("dashboard"); refreshCases(); }}/>;
  } else if (view === "vault") {
    content = <VaultPage cases={cases} openCase={openCase}/>;
  } else if (view === "reports") {
    content = <ReportCenter role={role}/>;
  } else if (view === "users" && canManageUsers(user)) {
    content = <UserManagementPage currentUser={user} officers={officers} roleLabels={roleLabels}/>;
  } else if (view === "officers-mgt" && role === "admin") {
    content = <OfficerManagePage/>;
  } else if (view === "lookup" && role === "admin") {
    content = <LookupManagePage/>;
  } else if (view === "roles" && role === "admin") {
    content = <RoleLabelsPage roleLabels={roleLabels} onUpdate={setRoleLabels}/>;
  } else if (view === "todos") {
    content = <TodoPage/>;
  } else if (view === "sla" && (role === "admin" || role === "dir_legal")) {
    content = <SlaSettingsPage currentUser={user}/>;
  } else if (view === "system" && role === "admin") {
    content = <SystemSettingsPage/>;
  } else if (view === "groups" && role === "admin") {
    content = <GroupsPage currentUser={user}/>;
  } else if (view === "my-group" && user?.leader_of_group) {
    content = <MyGroupPage currentUser={user}/>;
  } else if (view === "calendar") {
    content = <CalendarPage officers={officers} currentUser={user}/>;
  }

  const [showProfile, setShowProfile] = useState(false);

  const handleLogout = async () => {
    if (user.is_impersonating) {
      try {
        await api.stopImpersonating();
        window.location.reload();
      } catch(e) { alert(e.message); }
      return;
    }
    await api.logout().catch(() => {});
    onLogout();
  };

  const handleProfileSave = (saved, opts) => {
    setUser(u => ({ ...u, ...saved }));
    if (!opts?.silent) setShowProfile(false);
  };

  const handleStopImpersonating = async () => {
    try {
      await api.stopImpersonating();
      window.location.reload();
    } catch(e) { alert(e.message); }
  };

  const handleSwitchRole = async (newRole) => {
    try {
      await api.switchRole(newRole);
      window.location.reload(); // โหลดใหม่เพื่อให้เมนู/สิทธิ์ตรงกับบทบาทใหม่
    } catch(e) { alert(e.message); }
  };

  return (
    <div className="admin">
      {user.is_impersonating && (
        <div style={{
          position:'fixed', top:0, left:0, right:0, zIndex:9999,
          background:'#7c2d12', color:'#fff',
          padding:'7px 20px', display:'flex', alignItems:'center',
          gap:10, fontSize:13, fontWeight:500, boxShadow:'0 2px 8px rgba(0,0,0,.4)',
        }}>
          <Icon name="eye" style={{width:15,height:15,flexShrink:0}}/>
          <span style={{flex:1}}>
            คุณกำลังสวมสิทธิ์เป็น <b>{user.display_name}</b> ({user.username})
            &nbsp;·&nbsp; Admin จริง: <b>{user.impersonator_name}</b>
          </span>
          <button onClick={handleStopImpersonating} style={{
            background:'rgba(255,255,255,.18)', border:'1px solid rgba(255,255,255,.4)',
            color:'#fff', borderRadius:6, padding:'3px 14px', cursor:'pointer', fontWeight:600, fontSize:13,
          }}>
            ↩ คืนสิทธิ์ Admin
          </button>
        </div>
      )}
      <aside className="sidebar" style={user.is_impersonating ? {marginTop:36} : {}}>
        <div className="sb-brand">
          <img src="assets/ovec-logo.svg" alt=""/>
          <div><div className="t1">งานนิติการ สอศ.</div><div className="t2">ระบบบริหารงานนิติการ</div></div>
        </div>
        <nav className="sb-nav">
          {nav.map((n,i) => n.sec
            ? <div key={i} className="sb-section">{n.sec}</div>
            : <button key={i}
                className={"nav-item " + ((view===n.v || (view==="case-detail" && n.v==="cases")) ? "active" : "")}
                onClick={() => setView(n.v)}>
                <Icon name={n.ic}/> <span>{n.l}</span>
                {n.count ? <span className="count" style={{flex:"none"}}>{n.count}</span> : null}
              </button>
          )}
        </nav>
        {window.__APP_VERSION__ && (
          <div className="faint tiny" style={{padding:"12px 16px",borderTop:"1px solid var(--line)",textAlign:"center"}}>{window.__APP_VERSION__}</div>
        )}
      </aside>

      <main style={user.is_impersonating ? {marginTop:36} : {}}>
        <div className="topbar">
          <div className="vcenter" style={{gap:12}}>
            <span className="badge badge-maroon"><Icon name="shield" style={{width:13,height:13}}/> {roleLabel(role, roleLabels)}</span>
            <span className="faint sm">/ {sectionTitle}</span>
          </div>
          <div className="vcenter" style={{gap:8}}>
            <ThemeToggle theme={theme} setTheme={setTheme}/>
            <NotificationBell onOpenCase={openCase}/>
            <UserMenu user={user} role={role} roleLabels={roleLabels} onEditProfile={()=>setShowProfile(true)} onLogout={handleLogout} onSwitchRole={handleSwitchRole} size="sm"/>
          </div>
        </div>
        <div className="content">{content}</div>
      </main>

      {showProfile && (
        <ProfileModal user={user} onSave={handleProfileSave} onClose={()=>setShowProfile(false)}/>
      )}
    </div>
  );
}

/* ---------------- คลังสำนวน & ไฟล์ ---------------- */
function VaultPage({ cases, openCase }) {
  const rows = cases.flatMap(c => (c.files||[]).map(f => ({...f, cid:c.id, subject:c.subject})));
  return (
    <div className="fade-in">
      <PageHead title="คลังสำนวน & ไฟล์" sub="เอกสารและหลักฐานทั้งหมด จัดชั้นความลับและตรวจสอบสิทธิ์ก่อนเข้าถึง"/>
      <div className="notice notice-info" style={{marginBottom:18}}><Icon name="shieldCheck"/><div>ไฟล์ทุกชิ้นเก็บนอก web root · สแกนไวรัส · การดาวน์โหลดถูกบันทึกใน Audit log</div></div>
      <div className="card">
        <div className="table-wrap">
          <table className="tbl">
            <thead><tr><th>ไฟล์</th><th>สำนวน</th><th>ชั้นความลับ</th><th>ขนาด</th><th></th></tr></thead>
            <tbody>
              {rows.map((f,i) => (
                <tr key={i} onClick={() => openCase(f.cid)}>
                  <td><div className="vcenter"><Icon name="file" style={{width:18,height:18,color:"var(--maroon)"}}/><span style={{fontWeight:500}}>{f.n}</span></div></td>
                  <td><div className="code sm">{f.cid}</div><div className="faint tiny" style={{maxWidth:240,whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis"}}>{f.subject}</div></td>
                  <td><span className={"badge " + (CLASS[f.c]||CLASS.public).c}><Icon name="lock" style={{width:11,height:11}}/> {(CLASS[f.c]||CLASS.public).l}</span></td>
                  <td className="sm muted tnum">{f.s}</td>
                  <td><div className="vcenter" style={{gap:6}}><button className="icon-btn" style={{width:30,height:30}} onClick={e=>e.stopPropagation()}><Icon name="eye" style={{width:15,height:15}}/></button><button className="icon-btn" style={{width:30,height:30}} onClick={e=>e.stopPropagation()}><Icon name="download" style={{width:15,height:15}}/></button></div></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

/* ---------------- ศูนย์รายงาน ---------------- */
/* ---- helper: แปลงวันที่ พ.ศ. ---- */
function reportThaiDate(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  if (isNaN(d)) return iso;
  return d.toLocaleDateString('th-TH', { day:'numeric', month:'long', year:'numeric' });
}

/* ---- เปิดหน้าต่างพิมพ์รายงาน A4 1 หน้า ---- */
function printReportA4(title, bodyHtml) {
  const today = new Date().toLocaleDateString('th-TH', { day:'numeric', month:'long', year:'numeric' });
  const w = window.open('', '_blank', 'width=900,height=1000');
  if (!w) { alert('กรุณาอนุญาต popup เพื่อพิมพ์รายงาน'); return; }
  w.document.write(`<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><title>${title}</title>
  <style>
    @page { size: A4 portrait; margin: 16mm 16mm 14mm; }
    * { box-sizing: border-box; }
    body { font-family: "TH Sarabun New","Sarabun","Tahoma",sans-serif; color:#1a1a1a; margin:0; font-size:13px; line-height:1.5; }
    .wm { position:fixed; top:42%; left:50%; transform:translate(-50%,-50%) rotate(-30deg); font-size:120px; color:rgba(122,30,46,.06); font-weight:700; z-index:0; pointer-events:none; }
    .hd { text-align:center; border-bottom:2px solid #7a1e2e; padding-bottom:10px; margin-bottom:14px; position:relative; z-index:1; }
    .hd h1 { font-size:20px; margin:0; color:#7a1e2e; }
    .hd .sub { font-size:13px; color:#444; margin-top:2px; }
    .meta { display:flex; justify-content:space-between; font-size:12px; color:#555; margin-bottom:12px; position:relative; z-index:1; }
    h2.sec { font-size:15px; color:#7a1e2e; margin:14px 0 6px; border-left:4px solid #7a1e2e; padding-left:8px; position:relative; z-index:1; }
    table { width:100%; border-collapse:collapse; margin:4px 0 8px; position:relative; z-index:1; }
    th,td { border:1px solid #ccc; padding:4px 8px; text-align:left; font-size:12px; }
    th { background:#f3ecec; color:#7a1e2e; font-weight:600; }
    td.num, th.num { text-align:right; }
    .cards { display:flex; gap:8px; margin:6px 0 10px; position:relative; z-index:1; }
    .kpi { flex:1; border:1px solid #ddd; border-radius:6px; padding:8px 10px; text-align:center; }
    .kpi .n { font-size:22px; font-weight:700; color:#7a1e2e; }
    .kpi .l { font-size:11px; color:#666; }
    .ft { position:fixed; bottom:6mm; left:16mm; right:16mm; font-size:10px; color:#999; display:flex; justify-content:space-between; border-top:1px solid #eee; padding-top:4px; }
    .pill { display:inline-block; padding:1px 7px; border-radius:9px; font-size:11px; }
    .g{background:#e6f4ea;color:#1a7f37}.a{background:#fdf2e0;color:#9a6700}.r{background:#fbe9e9;color:#b42318}
  </style></head><body>
    <div class="wm">สำเนา</div>
    <div class="hd"><h1>สำนักงานคณะกรรมการการอาชีวศึกษา</h1><div class="sub">งานนิติการ — ${title}</div></div>
    <div class="meta"><span>วันที่ออกรายงาน: ${today}</span><span>เอกสารภายใน</span></div>
    ${bodyHtml}
    <div class="ft"><span>ระบบบริหารงานนิติการ สอศ.</span><span>${title} · ${today}</span></div>
    <script>window.onload=function(){setTimeout(function(){window.print();},250);};<\/script>
  </body></html>`);
  w.document.close();
}

/* ---- ดาวน์โหลด CSV ---- */
function downloadCSV(filename, rows) {
  const csv = rows.map(r => r.map(c => {
    const s = String(c ?? '');
    return /[",\n]/.test(s) ? '"' + s.replace(/"/g,'""') + '"' : s;
  }).join(',')).join('\r\n');
  const blob = new Blob(['﻿' + csv], { type:'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = filename; a.click();
  setTimeout(()=>URL.revokeObjectURL(url), 1000);
}

function ReportCenter({ role }) {
  const [cases, setCases] = useState(null);
  const [audit, setAudit] = useState(null);
  const [err, setErr] = useState('');

  useEffect(() => {
    api.getCases().then(setCases).catch(e=>setErr(e.message));
    api.getAuditReport().then(setAudit).catch(()=>{});
  }, []);

  const trackLabel = (t) => (TRACKS[t]?.label) || t || 'ไม่ระบุ';
  const esc = (s) => String(s ?? '').replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]));

  // ── คำนวณสรุป ──
  const buildStats = () => {
    const all = cases || [];
    const now = new Date();
    const ym = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0');
    const inMonth = (c) => (c.received || '').slice(0,7) === ym;
    const open = (c) => !['closed','rejected'].includes(c.status);
    return {
      total: all.length,
      monthNew: all.filter(inMonth).length,
      open: all.filter(open).length,
      closed: all.filter(c=>c.status==='closed').length,
      overdue: all.filter(c=>open(c) && c.sla==='r').length,
      amber: all.filter(c=>open(c) && c.sla==='a').length,
      byTrack: Object.keys(TRACKS).map(t => ({
        t, label: TRACKS[t].label,
        total: all.filter(c=>c.track===t).length,
        open:  all.filter(c=>c.track===t && open(c)).length,
        closed:all.filter(c=>c.track===t && c.status==='closed').length,
      })),
      byStatus: Object.keys(STATUS).map(s => ({ s, label: STATUS[s].label, n: all.filter(c=>c.status===s).length })).filter(x=>x.n>0),
      pending: all.filter(open).sort((a,b)=>{
        const rank = {r:0,a:1,g:2};
        if ((rank[a.sla]??3)!==(rank[b.sla]??3)) return (rank[a.sla]??3)-(rank[b.sla]??3);
        return (a.due||'9999').localeCompare(b.due||'9999');
      }),
    };
  };

  // ── รายงานประจำเดือน ──
  const monthlyHtml = () => {
    const s = buildStats();
    const monthName = new Date().toLocaleDateString('th-TH', { month:'long', year:'numeric' });
    return `
      <h2 class="sec">ภาพรวมประจำเดือน ${monthName}</h2>
      <div class="cards">
        <div class="kpi"><div class="n">${s.monthNew}</div><div class="l">รับเข้าใหม่ (เดือนนี้)</div></div>
        <div class="kpi"><div class="n">${s.open}</div><div class="l">อยู่ระหว่างดำเนินการ</div></div>
        <div class="kpi"><div class="n">${s.closed}</div><div class="l">เสร็จสิ้น (สะสม)</div></div>
        <div class="kpi"><div class="n">${s.overdue}</div><div class="l">เกินกำหนด</div></div>
      </div>
      <h2 class="sec">แยกตามสายงาน</h2>
      <table><thead><tr><th>สายงาน</th><th class="num">ทั้งหมด</th><th class="num">กำลังดำเนินการ</th><th class="num">เสร็จสิ้น</th></tr></thead><tbody>
        ${s.byTrack.map(r=>`<tr><td>${esc(r.label)}</td><td class="num">${r.total}</td><td class="num">${r.open}</td><td class="num">${r.closed}</td></tr>`).join('')}
      </tbody></table>
      <h2 class="sec">แยกตามสถานะ</h2>
      <table><thead><tr><th>สถานะ</th><th class="num">จำนวน</th></tr></thead><tbody>
        ${s.byStatus.map(r=>`<tr><td>${esc(r.label)}</td><td class="num">${r.n}</td></tr>`).join('')}
      </tbody></table>`;
  };

  // ── รายงานรายไตรมาส ──
  const quarterlyHtml = () => {
    const s = buildStats();
    const q = Math.floor(new Date().getMonth()/3)+1;
    const slaTotal = s.overdue + s.amber + (s.open - s.overdue - s.amber);
    const pct = (n) => slaTotal ? Math.round(n/slaTotal*100) : 0;
    return `
      <h2 class="sec">สรุปผลการดำเนินงาน ไตรมาสที่ ${q}/${new Date().getFullYear()+543}</h2>
      <div class="cards">
        <div class="kpi"><div class="n">${s.total}</div><div class="l">เรื่องทั้งหมด</div></div>
        <div class="kpi"><div class="n">${s.closed}</div><div class="l">เสร็จสิ้น</div></div>
        <div class="kpi"><div class="n">${s.total? Math.round(s.closed/s.total*100):0}%</div><div class="l">อัตราเสร็จสิ้น</div></div>
      </div>
      <h2 class="sec">เปรียบเทียบตามสายงาน</h2>
      <table><thead><tr><th>สายงาน</th><th class="num">ทั้งหมด</th><th class="num">เสร็จสิ้น</th><th class="num">% เสร็จสิ้น</th></tr></thead><tbody>
        ${s.byTrack.map(r=>`<tr><td>${esc(r.label)}</td><td class="num">${r.total}</td><td class="num">${r.closed}</td><td class="num">${r.total?Math.round(r.closed/r.total*100):0}%</td></tr>`).join('')}
      </tbody></table>
      <h2 class="sec">สถานะ SLA (เรื่องที่กำลังดำเนินการ)</h2>
      <table><thead><tr><th>ระดับ</th><th class="num">จำนวน</th><th class="num">สัดส่วน</th></tr></thead><tbody>
        <tr><td><span class="pill r">เกินกำหนด</span></td><td class="num">${s.overdue}</td><td class="num">${pct(s.overdue)}%</td></tr>
        <tr><td><span class="pill a">ใกล้ครบกำหนด</span></td><td class="num">${s.amber}</td><td class="num">${pct(s.amber)}%</td></tr>
        <tr><td><span class="pill g">ตามกำหนด</span></td><td class="num">${s.open - s.overdue - s.amber}</td><td class="num">${pct(s.open - s.overdue - s.amber)}%</td></tr>
      </tbody></table>`;
  };

  // ── รายการเรื่องคงค้าง ──
  const pendingHtml = () => {
    const s = buildStats();
    const rows = s.pending.slice(0, 22); // จำกัดให้พอดี 1 หน้า A4
    const slaPill = (v) => v==='r'?'<span class="pill r">เกิน</span>':v==='a'?'<span class="pill a">ใกล้ครบ</span>':'<span class="pill g">ปกติ</span>';
    return `
      <h2 class="sec">เรื่องคงค้าง ${s.open} เรื่อง (เกินกำหนด ${s.overdue} · ใกล้ครบ ${s.amber})</h2>
      <table><thead><tr><th>รหัส</th><th>เรื่อง</th><th>สายงาน</th><th>สถานะ</th><th>ครบกำหนด</th><th>SLA</th></tr></thead><tbody>
        ${rows.map(c=>`<tr>
          <td>${esc(c.id)}</td>
          <td>${esc((c.subject||'').slice(0,42))}</td>
          <td>${esc(trackLabel(c.track))}</td>
          <td>${esc(STATUS[c.status]?.label||c.status)}</td>
          <td>${c.due?reportThaiDate(c.due):'—'}</td>
          <td>${slaPill(c.sla)}</td>
        </tr>`).join('')}
      </tbody></table>
      ${s.pending.length>22?`<p style="font-size:11px;color:#888">* แสดง 22 จากทั้งหมด ${s.pending.length} เรื่อง (เรียงตามความเสี่ยง) — ดูทั้งหมดในระบบหรือไฟล์ CSV</p>`:''}`;
  };

  // ── Audit & PDPA ──
  const auditHtml = () => {
    if (!audit) return '<p>ไม่สามารถโหลดข้อมูล audit ได้</p>';
    const actLabel = { view_case:'เปิดดูสำนวน', update_case:'แก้ไขสำนวน', create_case:'สร้างสำนวน', login:'เข้าสู่ระบบ',
      event_update:'อัปเดตขั้นตอน', event_create:'สร้างขั้นตอน', approve_proposal:'อนุมัติข้อเสนอ', propose_case:'เสนอมอบหมาย',
      case_closed:'ปิดเรื่อง', case_reopen:'เปิดเรื่องใหม่' };
    return `
      <h2 class="sec">สรุปการเข้าถึงข้อมูล (30 วันล่าสุด)</h2>
      <div class="cards">
        <div class="kpi"><div class="n">${audit.total}</div><div class="l">เหตุการณ์ทั้งหมด</div></div>
        <div class="kpi"><div class="n">${audit.pdpa.total_cases}</div><div class="l">สำนวนในระบบ</div></div>
        <div class="kpi"><div class="n">${audit.pdpa.anon_cases}</div><div class="l">ปกปิดชื่อผู้ร้อง</div></div>
        <div class="kpi"><div class="n">${audit.pdpa.classified_cases}</div><div class="l">ชั้นความลับ</div></div>
      </div>
      <h2 class="sec">กิจกรรมแยกตามประเภท</h2>
      <table><thead><tr><th>ประเภทเหตุการณ์</th><th class="num">จำนวนครั้ง</th></tr></thead><tbody>
        ${audit.by_action.map(r=>`<tr><td>${esc(actLabel[r.action]||r.action)}</td><td class="num">${r.n}</td></tr>`).join('')}
      </tbody></table>
      <h2 class="sec">ผู้ใช้ที่มีกิจกรรมสูงสุด</h2>
      <table><thead><tr><th>ผู้ใช้</th><th class="num">จำนวนครั้ง</th></tr></thead><tbody>
        ${audit.top_users.map(r=>`<tr><td>${esc(r.name)}</td><td class="num">${r.n}</td></tr>`).join('')}
      </tbody></table>
      <p style="font-size:11px;color:#888">* สอดคล้องตาม พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล (PDPA) — บันทึกการเข้าถึงเพื่อการตรวจสอบ</p>`;
  };

  // ── CSV ──
  const monthlyCSV = () => { const s=buildStats(); return [['สายงาน','ทั้งหมด','กำลังดำเนินการ','เสร็จสิ้น'],...s.byTrack.map(r=>[r.label,r.total,r.open,r.closed])]; };
  const pendingCSV = () => { const s=buildStats(); return [['รหัส','เรื่อง','สายงาน','สถานะ','ครบกำหนด','SLA'],...s.pending.map(c=>[c.id,c.subject,trackLabel(c.track),STATUS[c.status]?.label||c.status,c.due||'',c.sla||''])]; };

  const reports = [
    {ic:"calendar", t:"รายงานประจำเดือน",    d:"สรุปเรื่องรับเข้า ดำเนินการ และเสร็จสิ้น รายเดือน พร้อมสถานะ SLA", html:monthlyHtml, csv:monthlyCSV},
    {ic:"chart",    t:"รายงานรายไตรมาส",     d:"ภาพรวมผลการดำเนินงานเชิงเปรียบเทียบ แยกตามสายงานและกลุ่มงาน", html:quarterlyHtml, csv:monthlyCSV},
    {ic:"alert",    t:"รายการเรื่องคงค้าง",   d:"เรื่องที่ยังไม่แล้วเสร็จ เรียงตามความเสี่ยงเกินกำหนด", html:pendingHtml, csv:pendingCSV},
    {ic:"shield",   t:"รายงาน Audit & PDPA", d:"ประวัติการเข้าถึงข้อมูลและไฟล์ การจัดการข้อมูลส่วนบุคคล", html:auditHtml, csv:null},
  ];

  const ready = cases !== null;

  return (
    <div className="fade-in">
      <PageHead title={["dir_admin","secretary","deputy_secretary"].includes(role)?"รายงานผู้บริหาร":"ศูนย์รายงาน"} sub="ออกรายงานราชการ พร้อมหัวกระดาษ ลายน้ำ 'สำเนา' พิมพ์ลงกระดาษ A4 ได้ 1 หน้า"/>
      {err && <div className="notice notice-danger" style={{marginBottom:12}}>{err}</div>}
      <div className="grid" style={{gridTemplateColumns:"repeat(2,1fr)"}}>
        {reports.map((r,i) => (
          <div key={i} className="card card-pad" style={{display:"flex",gap:16,alignItems:"flex-start"}}>
            <div className="fi" style={{width:46,height:46,borderRadius:12,background:"var(--maroon-50)",color:"var(--maroon)",display:"grid",placeItems:"center",flex:"none"}}><Icon name={r.ic} style={{width:22,height:22}}/></div>
            <div style={{flex:1}}>
              <h3 style={{fontSize:16}}>{r.t}</h3>
              <p className="muted sm" style={{margin:"6px 0 14px"}}>{r.d}</p>
              <div className="row" style={{gap:8}}>
                <button className="btn btn-outline btn-sm" disabled={!ready}
                  onClick={()=>printReportA4(r.t, r.html())}>
                  <Icon name="download" style={{width:15,height:15}}/> {ready?'พิมพ์ / PDF':'กำลังโหลด…'}
                </button>
                {r.csv && <button className="btn btn-outline btn-sm" disabled={!ready}
                  onClick={()=>downloadCSV(r.t+'.csv', r.csv())}>
                  <Icon name="download" style={{width:15,height:15}}/> CSV
                </button>}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ---------------- Todo list (Admin) ---------------- */
function formatDuration(createdAt, completedAt) {
  const diff = Math.max(0, Math.round((new Date(completedAt) - new Date(createdAt)) / 60000));
  if (diff < 60) return diff + ' นาที';
  const h = Math.floor(diff / 60), m = diff % 60;
  if (h < 24) return h + ' ชั่วโมง' + (m ? ' ' + m + ' นาที' : '');
  const d = Math.floor(h / 24), hr = h % 24;
  return d + ' วัน' + (hr ? ' ' + hr + ' ชั่วโมง' : '');
}

function fmtDt(ts) {
  if (!ts) return '—';
  const d = new Date(ts);
  return d.toLocaleDateString('th-TH', { year:'numeric', month:'short', day:'numeric' })
       + ' ' + d.toLocaleTimeString('th-TH', { hour:'2-digit', minute:'2-digit' });
}

function TodoItem({ todo, onToggle, onDelete, onEdit }) {
  const [expanded, setExpanded] = useState(false);
  const [busy, setBusy] = useState(false);
  const [editing, setEditing] = useState(false);
  const [editTitle, setEditTitle] = useState(todo.title);
  const [editDetail, setEditDetail] = useState(todo.detail || '');

  const toggle = async () => {
    setBusy(true);
    await onToggle(todo).catch(() => {});
    setBusy(false);
  };

  const saveEdit = async (e) => {
    e.preventDefault();
    if (!editTitle.trim()) return;
    await onEdit(todo.id, { title: editTitle.trim(), detail: editDetail.trim() });
    setEditing(false);
  };

  return (
    <div className="card" style={{padding:"14px 16px",display:"flex",gap:12,alignItems:"flex-start",opacity:todo.done ? .72 : 1,transition:"opacity .2s"}}>
      <button onClick={toggle} disabled={busy} aria-label={todo.done ? "ยังไม่เสร็จ" : "ทำเสร็จแล้ว"}
        style={{flexShrink:0,marginTop:2,width:20,height:20,borderRadius:5,border:"2px solid " + (todo.done ? "var(--ok)" : "var(--line)"),
          background:todo.done ? "var(--ok)" : "transparent",color:"#fff",cursor:"pointer",display:"grid",placeItems:"center",padding:0,transition:"all .18s"}}>
        {todo.done && <Icon name="check" style={{width:11,height:11}}/>}
      </button>

      <div style={{flex:1,minWidth:0}}>
        {editing ? (
          <form onSubmit={saveEdit} style={{display:"flex",flexDirection:"column",gap:8}}>
            <input className="input" value={editTitle} onChange={e=>setEditTitle(e.target.value)} autoFocus required
              style={{fontWeight:600,fontSize:15}}/>
            <textarea className="input" value={editDetail} onChange={e=>setEditDetail(e.target.value)} rows={2}
              placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)" style={{resize:"vertical",fontSize:13}}/>
            <div className="vcenter" style={{gap:8}}>
              <button type="submit" className="btn btn-primary btn-sm">บันทึก</button>
              <button type="button" className="btn btn-ghost btn-sm" onClick={()=>setEditing(false)}>ยกเลิก</button>
            </div>
          </form>
        ) : (
          <>
            <div className="vcenter" style={{gap:8,flexWrap:"wrap"}}>
              <span style={{fontWeight:600,fontSize:15,textDecoration:todo.done ? "line-through" : "none",color:todo.done ? "var(--ink-3)" : "var(--ink)"}}>{todo.title}</span>
              {todo.detail && (
                <button className="btn btn-ghost btn-sm" style={{padding:"0 6px",height:22,fontSize:12}} onClick={()=>setExpanded(v=>!v)}>
                  {expanded ? "ซ่อน" : "รายละเอียด"}
                </button>
              )}
            </div>
            {todo.detail && expanded && (
              <p className="sm muted" style={{margin:"6px 0 0",whiteSpace:"pre-wrap",lineHeight:1.7}}>{todo.detail}</p>
            )}
            <div className="vcenter" style={{gap:12,marginTop:6,flexWrap:"wrap"}}>
              <span className="tiny faint vcenter" style={{gap:4}}><Icon name="plus" style={{width:11,height:11}}/>เพิ่ม {fmtDt(todo.created_at)}</span>
              {todo.done && todo.completed_at && (
                <>
                  <span className="tiny faint vcenter" style={{gap:4,color:"var(--ok)"}}><Icon name="check" style={{width:11,height:11}}/>เสร็จ {fmtDt(todo.completed_at)}</span>
                  <span className="tiny badge badge-ok" style={{padding:"1px 7px"}}>ใช้เวลา {formatDuration(todo.created_at, todo.completed_at)}</span>
                </>
              )}
            </div>
          </>
        )}
      </div>

      {!editing && (
        <div className="vcenter" style={{gap:4,flexShrink:0}}>
          <button className="icon-btn" style={{width:28,height:28}} title="แก้ไข" onClick={()=>{ setEditing(true); setEditTitle(todo.title); setEditDetail(todo.detail||''); }}>
            <Icon name="edit" style={{width:14,height:14}}/>
          </button>
          <button className="icon-btn" style={{width:28,height:28,color:"var(--danger)"}} title="ลบ"
            onClick={()=>{ if(confirm('ลบรายการ "' + todo.title + '" ออกจากรายการหรือไม่?')) onDelete(todo.id); }}>
            <Icon name="x" style={{width:14,height:14}}/>
          </button>
        </div>
      )}
    </div>
  );
}

function TodoPage() {
  const [todos,   setTodos]   = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter,  setFilter]  = useState('pending');
  const [showAdd, setShowAdd] = useState(false);
  const [newTitle,  setNewTitle]  = useState('');
  const [newDetail, setNewDetail] = useState('');
  const [adding, setAdding] = useState(false);
  const [err, setErr] = useState('');

  useEffect(() => {
    api.listTodos().then(setTodos).catch(e=>setErr(e.message)).finally(()=>setLoading(false));
  }, []);

  const addTodo = async (e) => {
    e.preventDefault();
    if (!newTitle.trim()) return;
    setAdding(true); setErr('');
    try {
      const created = await api.createTodo({ title: newTitle.trim(), detail: newDetail.trim() });
      setTodos(ts => [created, ...ts]);
      setNewTitle(''); setNewDetail(''); setShowAdd(false);
      setFilter('pending');
    } catch(e) { setErr(e.message); }
    setAdding(false);
  };

  const toggleDone = async (todo) => {
    const updated = await api.patchTodo(todo.id, { done: !todo.done });
    setTodos(ts => ts.map(t => t.id === todo.id ? updated : t));
  };

  const editTodo = async (id, patch) => {
    const updated = await api.patchTodo(id, patch);
    setTodos(ts => ts.map(t => t.id === id ? updated : t));
  };

  const deleteTodo = async (id) => {
    await api.deleteTodo(id).catch(() => {});
    setTodos(ts => ts.filter(t => t.id !== id));
  };

  const counts = {
    all:     todos.length,
    pending: todos.filter(t=>!t.done).length,
    done:    todos.filter(t=>!!t.done).length,
  };
  const filtered = todos.filter(t =>
    filter === 'all' ? true : filter === 'done' ? !!t.done : !t.done
  );

  return (
    <div className="fade-in" style={{maxWidth:760}}>
      <PageHead title="รายการที่ต้องทำ" sub="บันทึกงานที่ต้องดำเนินการ และติดตามเวลาที่ใช้">
        <button className="btn btn-primary btn-sm" onClick={()=>setShowAdd(v=>!v)}>
          <Icon name="plus" style={{width:15,height:15}}/> เพิ่มรายการ
        </button>
      </PageHead>

      {err && <div className="notice notice-err" style={{marginBottom:16}}><Icon name="alert"/><div>{err}</div></div>}

      {showAdd && (
        <div className="card card-pad" style={{marginBottom:16}}>
          <form onSubmit={addTodo} style={{display:"flex",flexDirection:"column",gap:10}}>
            <div className="field">
              <label>ชื่องาน <span className="req">*</span></label>
              <input className="input" value={newTitle} onChange={e=>setNewTitle(e.target.value)} placeholder="ระบุชื่องานที่ต้องทำ" autoFocus required/>
            </div>
            <div className="field">
              <label>รายละเอียด</label>
              <textarea className="input" value={newDetail} onChange={e=>setNewDetail(e.target.value)} rows={2}
                placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)" style={{resize:"vertical"}}/>
            </div>
            <div className="btn-row">
              <button type="button" className="btn btn-ghost" onClick={()=>{ setShowAdd(false); setNewTitle(''); setNewDetail(''); }}>ยกเลิก</button>
              <button type="submit" className="btn btn-primary" disabled={adding || !newTitle.trim()}>
                {adding ? <LoadingSpinner/> : <><Icon name="plus" style={{width:15,height:15}}/> เพิ่ม</>}
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="vcenter" style={{gap:6,marginBottom:14,flexWrap:"wrap"}}>
        {[['pending','รอดำเนินการ'],['done','เสร็จแล้ว'],['all','ทั้งหมด']].map(([k,l])=>(
          <button key={k} onClick={()=>setFilter(k)}
            className={"btn btn-sm " + (filter===k ? "btn-primary" : "btn-ghost")}
            style={{padding:"4px 14px"}}>
            {l} <span className={"badge " + (filter===k?"badge-maroon":"")} style={{marginLeft:4,padding:"1px 6px",fontSize:11}}>{counts[k]}</span>
          </button>
        ))}
      </div>

      {loading ? <LoadingSpinner/> : filtered.length === 0 ? (
        <div className="card card-pad" style={{textAlign:"center",color:"var(--ink-3)",padding:"40px 24px"}}>
          <Icon name="checkCircle" style={{width:36,height:36,marginBottom:10,opacity:.35}}/>
          <div className="sm">{filter==='done' ? 'ยังไม่มีรายการที่เสร็จแล้ว' : filter==='pending' ? 'ไม่มีรายการที่รอดำเนินการ' : 'ยังไม่มีรายการ'}</div>
        </div>
      ) : (
        <div style={{display:"flex",flexDirection:"column",gap:8}}>
          {filtered.map(todo => (
            <TodoItem key={todo.id} todo={todo} onToggle={toggleDone} onDelete={deleteTodo} onEdit={editTodo}/>
          ))}
        </div>
      )}
    </div>
  );
}

/* ---------------- Root ---------------- */
function App() {
  const [theme, setTheme] = useTheme();
  const [user,  setUser]  = useState(window.__INITIAL_USER__ || null);
  const [screen, setScreen] = useState(
    window.__INITIAL_USER__ ? "admin" : "public"
  );
  const [pub, setPub] = useState(() => {
    if (!window.__INITIAL_USER__) {
      const v = new URLSearchParams(window.location.search).get('view');
      if (v === 'form' || v === 'track') return { view: v, params: null };
    }
    return { view: 'home', params: null };
  });

  const go = (view, params) => {
    if (view === "login") { setScreen("login"); return; }
    if (view === "admin") { setScreen("admin"); return; }
    setScreen("public"); setPub({ view, params: params||null });
    const base = (window.__APP_BASE__ || '').replace(/\/$/, '');
    const url = view === 'home' ? base + '/' : base + '/?view=' + view;
    history.pushState(null, '', url);
  };

  const handleLogin = (loggedUser) => {
    setUser(loggedUser);
    setScreen("admin");
  };

  const handleLogout = () => {
    setUser(null);
    setScreen("public");
    setPub({ view:"home", params:null });
  };

  return (
    <>
      {screen === "public" && <>
        <PubHeader go={go} active={pub.view} theme={theme} setTheme={setTheme}/>
        {pub.view === "home"  && <PublicHome go={go}/>}
        {pub.view === "form"  && <ComplaintForm go={go}/>}
        {pub.view === "track" && <TrackStatus go={go} preset={pub.params}/>}
      </>}
      {screen === "login" && <AdminLogin go={go} onLogin={handleLogin}/>}
      {screen === "admin" && user && <AdminApp user={user} setUser={setUser} go={go} theme={theme} setTheme={setTheme} onLogout={handleLogout}/>}
    </>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App/>);
