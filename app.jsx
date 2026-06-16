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
          <h1 style={{fontSize:32,fontWeight:700,letterSpacing:"-.02em"}}>ระบบบริหารจัดการ<br/>งานนิติการ</h1>
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
const ROLE_LABELS = {
  officer:   "เจ้าหน้าที่นิติการ / ธุรการ",
  dir_legal: "ผอ.กลุ่มนิติการ",
  dir_admin: "ผอ.สำนักอำนวยการ",
  admin:     "ผู้ดูแลระบบ",
};

function canManageUsers(user) {
  return user?.role === 'admin' || !!user?.can_manage_users;
}

function navFor(role, counts, user) {
  if (role === "admin") return [
    {sec:"ระบบ"},
    {v:"users",     ic:"users",    l:"จัดการผู้ใช้"},
    {v:"dashboard", ic:"pie",      l:"ภาพรวม"},
    {v:"cases",     ic:"inbox",    l:"สำนวนทั้งหมด"},
    {v:"reports",   ic:"chart",    l:"รายงาน"},
  ];
  if (role === "officer") return [
    {sec:"การดำเนินงาน"},
    {v:"dashboard", ic:"home",     l:"แดชบอร์ด"},
    {v:"cases",     ic:"inbox",    l:"จัดการเรื่อง", count:counts.newQ},
    {v:"import",    ic:"filePlus", l:"นำเข้าเรื่องจากเอกสาร"},
    {v:"vault",     ic:"layers",   l:"คลังสำนวน & ไฟล์"},
    {sec:"ระบบ"},
    {v:"reports",   ic:"chart",    l:"รายงาน"},
    ...(canManageUsers(user) ? [{sec:"ระบบ"},{v:"users",ic:"users",l:"จัดการผู้ใช้"}] : []),
  ];
  if (role === "dir_legal") return [
    {sec:"กำกับงานสอบสวน"},
    {v:"dashboard", ic:"gavel",    l:"ติดตามการสอบสวน"},
    {v:"cases",     ic:"inbox",    l:"สำนวนทั้งหมด"},
    {sec:"ระบบ"},
    {v:"reports",   ic:"chart",    l:"รายงานกลุ่ม"},
  ];
  return [
    {sec:"ภาพรวมผู้บริหาร"},
    {v:"dashboard", ic:"pie",      l:"ภาพรวมสำนัก"},
    {v:"cases",     ic:"inbox",    l:"สำนวนทั้งหมด"},
    {sec:"ระบบ"},
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
        <form onSubmit={submit} style={{padding:"0 24px 24px",display:"flex",flexDirection:"column",gap:14,overflowY:"auto",flex:1}}>
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

          <div className="btn-row" style={{marginTop:4}}>
            <button type="button" className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? <LoadingSpinner/> : "บันทึก"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

/* ---------------- User menu (dropdown) ---------------- */
function UserMenu({ user, role, onEditProfile, onLogout, size = "md" }) {
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
          <div style={{lineHeight:1.2}}><div className="sm" style={{fontWeight:600}}>{(user.display_name||"").split(" ")[0]}</div></div>
        ) : (
          <div style={{flex:1,minWidth:0}}>
            <div className="sm" style={{fontWeight:600,whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis"}}>{user.display_name}</div>
            <div className="faint tiny">{ROLE_LABELS[role]}</div>
          </div>
        )}
        <Icon name="chevD" style={{width:14,height:14,flexShrink:0,opacity:.6}}/>
      </button>
      {open && (
        <div className="card" style={{position:"absolute",bottom:isSm?"auto":"calc(100% + 6px)",top:isSm?"calc(100% + 6px)":"auto",right:0,minWidth:190,padding:6,zIndex:100,boxShadow:"0 8px 30px rgba(0,0,0,.18)"}}>
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

function AdminApp({ user, setUser, go, theme, setTheme, onLogout }) {
  const role = user.role;
  const [view, setView]         = useState("dashboard");
  const [sel,  setSel]          = useState(null);
  const [cases, setCases]       = useState([]);
  const [officers, setOfficers] = useState([]);
  const [loading, setLoading]   = useState(true);

  useEffect(() => {
    Promise.all([api.getCases(), api.getOfficers()])
      .then(([c, o]) => { setCases(c); setOfficers(o); })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const refreshCases = async () => {
    const fresh = await api.getCases().catch(() => cases);
    setCases(fresh);
  };

  const counts = { newQ: cases.filter(c=>["received","screening"].includes(c.status)).length };
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
    dashboard:"แดชบอร์ด", cases:"จัดการเรื่อง",
    "case-detail":"รายละเอียดสำนวน", import:"นำเข้าเรื่อง",
    vault:"คลังสำนวน", reports:"รายงาน", users:"จัดการผู้ใช้",
  }[view] || "";

  let content;
  if (loading) {
    content = <LoadingSpinner/>;
  } else if (view === "case-detail") {
    content = <CaseDetail cid={sel} cases={cases} officers={officers} back={()=>setView("cases")} updateCase={updateCase} role={role}/>;
  } else if (view === "dashboard") {
    content = role === "officer"
      ? <OfficerDashboard cases={cases} officers={officers} openCase={openCase} setView={setView}/>
      : role === "dir_legal"
      ? <DirLegalDashboard cases={cases} officers={officers} openCase={openCase} setView={setView}/>
      : <DirAdminDashboard cases={cases} officers={officers} setView={setView}/>;
  } else if (view === "cases") {
    content = <CaseListPage cases={cases} officers={officers} openCase={openCase}/>;
  } else if (view === "import") {
    content = <ImportDocument back={()=>{ setView("dashboard"); refreshCases(); }}/>;
  } else if (view === "vault") {
    content = <VaultPage cases={cases} openCase={openCase}/>;
  } else if (view === "reports") {
    content = <ReportCenter role={role}/>;
  } else if (view === "users" && canManageUsers(user)) {
    content = <UserManagementPage currentUser={user} officers={officers}/>;
  }

  const [showProfile, setShowProfile] = useState(false);

  const handleLogout = async () => {
    await api.logout().catch(() => {});
    onLogout();
  };

  const handleProfileSave = (saved, opts) => {
    setUser(u => ({ ...u, ...saved }));
    if (!opts?.silent) setShowProfile(false);
  };

  return (
    <div className="admin">
      <aside className="sidebar">
        <div className="sb-brand">
          <img src="assets/ovec-logo.svg" alt=""/>
          <div><div className="t1">งานนิติการ สอศ.</div><div className="t2">ระบบบริหารจัดการ</div></div>
        </div>
        <nav className="sb-nav">
          {nav.map((n,i) => n.sec
            ? <div key={i} className="sb-section">{n.sec}</div>
            : <button key={i}
                className={"nav-item " + ((view===n.v || (view==="case-detail" && n.v==="cases")) ? "active" : "")}
                onClick={() => setView(n.v)}>
                <Icon name={n.ic}/> <span>{n.l}</span>
                {n.count ? <span className="count">{n.count}</span> : null}
              </button>
          )}
        </nav>
      </aside>

      <main>
        <div className="topbar">
          <div className="vcenter" style={{gap:12}}>
            <span className="badge badge-maroon"><Icon name="shield" style={{width:13,height:13}}/> {ROLE_LABELS[role]}</span>
            <span className="faint sm">/ {sectionTitle}</span>
          </div>
          <div className="vcenter" style={{gap:12}}>
            <ThemeToggle theme={theme} setTheme={setTheme}/>
            <button className="icon-btn" style={{position:"relative"}}><Icon name="bell"/><span style={{position:"absolute",top:8,right:9,width:7,height:7,borderRadius:"50%",background:"var(--danger)"}}></span></button>
            <UserMenu user={user} role={role} onEditProfile={()=>setShowProfile(true)} onLogout={handleLogout} size="sm"/>
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
                  <td><span className={"badge " + CLASS[f.c].c}><Icon name="lock" style={{width:11,height:11}}/> {CLASS[f.c].l}</span></td>
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
function ReportCenter({ role }) {
  const reports = [
    {ic:"calendar", t:"รายงานประจำเดือน",    d:"สรุปเรื่องรับเข้า ดำเนินการ และเสร็จสิ้น รายเดือน พร้อมสถานะ SLA"},
    {ic:"chart",    t:"รายงานรายไตรมาส",     d:"ภาพรวมผลการดำเนินงานเชิงเปรียบเทียบ แยกตามสายงานและกลุ่มงาน"},
    {ic:"alert",    t:"รายการเรื่องคงค้าง",   d:"เรื่องที่ยังไม่แล้วเสร็จ เรียงตามความเสี่ยงเกินกำหนด"},
    {ic:"shield",   t:"รายงาน Audit & PDPA", d:"ประวัติการเข้าถึงข้อมูลและไฟล์ การจัดการข้อมูลส่วนบุคคล"},
  ];
  return (
    <div className="fade-in">
      <PageHead title={role==="dir_admin"?"รายงานผู้บริหาร":"ศูนย์รายงาน"} sub="ออกรายงานราชการ พร้อมหัวกระดาษ ลายน้ำ 'สำเนา' และปกปิดข้อมูลส่วนบุคคลอัตโนมัติ"/>
      <div className="grid" style={{gridTemplateColumns:"repeat(2,1fr)"}}>
        {reports.map((r,i) => (
          <div key={i} className="card card-pad" style={{display:"flex",gap:16,alignItems:"flex-start"}}>
            <div className="fi" style={{width:46,height:46,borderRadius:12,background:"var(--maroon-50)",color:"var(--maroon)",display:"grid",placeItems:"center",flex:"none"}}><Icon name={r.ic} style={{width:22,height:22}}/></div>
            <div style={{flex:1}}>
              <h3 style={{fontSize:16}}>{r.t}</h3>
              <p className="muted sm" style={{margin:"6px 0 14px"}}>{r.d}</p>
              <div className="row" style={{gap:8}}>
                <button className="btn btn-outline btn-sm"><Icon name="download" style={{width:15,height:15}}/> PDF</button>
                <button className="btn btn-outline btn-sm"><Icon name="download" style={{width:15,height:15}}/> CSV</button>
              </div>
            </div>
          </div>
        ))}
      </div>
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
  const [pub, setPub] = useState({ view:"home", params:null });

  const go = (view, params) => {
    if (view === "login") { setScreen("login"); return; }
    if (view === "admin") { setScreen("admin"); return; }
    setScreen("public"); setPub({ view, params: params||null });
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
