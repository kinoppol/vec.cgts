/* ============================================================
   admin-officer.jsx — เจ้าหน้าที่นิติการ/ธุรการ
   แดชบอร์ด · จัดการเรื่อง · รายละเอียดสำนวน · นำเข้าเอกสาร · แต่งตั้งผู้สอบสวน
   ============================================================ */

function PageHead({ title, sub, children }) {
  return (
    <div className="page-head between" style={{alignItems:"flex-start"}}>
      <div><h1>{title}</h1>{sub && <div className="sub">{sub}</div>}</div>
      {children && <div className="vcenter">{children}</div>}
    </div>
  );
}

function StatCard({ ic, lbl, num, sub, tone }) {
  const tones = {
    maroon:{bg:"var(--maroon-50)",c:"var(--maroon)"},
    info:{bg:"var(--info-bg)",c:"var(--info)"},
    warn:{bg:"var(--warn-bg)",c:"var(--warn)"},
    ok:{bg:"var(--ok-bg)",c:"var(--ok)"},
    danger:{bg:"var(--danger-bg)",c:"var(--danger)"},
  };
  const t = tones[tone]||tones.maroon;
  return (
    <div className="stat">
      <div className="between" style={{alignItems:"flex-start"}}>
        <div className="lbl">{lbl}</div>
        <div className="ic" style={{background:t.bg,color:t.c}}><Icon name={ic}/></div>
      </div>
      <div className="num tnum" style={{color:t.c}}>{num}</div>
      {sub && <div className="sub">{sub}</div>}
    </div>
  );
}

/* ---------------- แดชบอร์ดเจ้าหน้าที่ ---------------- */
function OfficerDashboard({ cases, officers, openCase, setView }) {
  const cnt = (f)=>cases.filter(f).length;
  const newQ = cases.filter(c=>["received","screening"].includes(c.status));
  const mine = cases.filter(c=>c.assignee==="o2"); // TODO: use current officer id
  return (
    <div className="fade-in">
      <PageHead title="แดชบอร์ดเจ้าหน้าที่นิติการ" sub="ภาพรวมเรื่องร้องเรียน–ร้องทุกข์ในความรับผิดชอบ">
        <button className="btn btn-outline" onClick={()=>setView("import")}><Icon name="filePlus" style={{width:16,height:16}}/> นำเข้าเรื่องจากเอกสาร</button>
        <button className="btn btn-primary" onClick={()=>setView("cases")}><Icon name="inbox" style={{width:16,height:16}}/> จัดการเรื่องทั้งหมด</button>
      </PageHead>

      <div className="grid" style={{gridTemplateColumns:"repeat(4,1fr)",marginBottom:22}}>
        <StatCard ic="inbox" lbl="เรื่องใหม่รอคัดกรอง" num={cnt(c=>["received","screening"].includes(c.status))} sub="ต้องดำเนินการลงทะเบียน" tone="info"/>
        <StatCard ic="clock" lbl="อยู่ระหว่างดำเนินการ" num={cnt(c=>["assigned","investigating","reporting"].includes(c.status))} sub="กำลังสอบสวน/พิจารณา" tone="maroon"/>
        <StatCard ic="alert" lbl="ใกล้/เกินกำหนด SLA" num={cnt(c=>["a","r"].includes(c.sla)&&c.status!=="closed")} sub="ต้องเร่งติดตาม" tone="warn"/>
        <StatCard ic="checkCircle" lbl="เสร็จสิ้น (เดือนนี้)" num={cnt(c=>c.status==="closed")} sub="ปิดสำนวนแล้ว" tone="ok"/>
      </div>

      <div className="grid" style={{gridTemplateColumns:"1.6fr 1fr",alignItems:"start"}}>
        <div className="card">
          <div className="card-h"><h3>เรื่องรอคัดกรอง / ลงทะเบียน</h3><button className="btn btn-ghost btn-sm" onClick={()=>setView("cases")}>ดูทั้งหมด <Icon name="chevR" style={{width:14,height:14}}/></button></div>
          <div className="table-wrap">
            <table className="tbl">
              <thead><tr><th>รหัส/เลขรับ</th><th>เรื่อง</th><th>ช่องทาง</th><th>สถานะ</th><th></th></tr></thead>
              <tbody>
                {newQ.map(c=>(
                  <tr key={c.id} onClick={()=>openCase(c.id)}>
                    <td><div className="code">{c.id}</div><div className="faint tiny">{c.reg!=="—"?c.reg:"ยังไม่ลงทะเบียน"}</div></td>
                    <td style={{maxWidth:240}}><div style={{fontWeight:500,whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis"}}>{c.subject}</div>{c.anon&&<span className="badge badge-warn" style={{marginTop:4}}><span className="dot"></span>ไม่ประสงค์ออกนาม</span>}</td>
                    <td className="sm muted">{c.channel}</td>
                    <td><StatusBadge s={c.status}/></td>
                    <td><Icon name="chevR" style={{width:16,height:16,color:"var(--ink-3)"}}/></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        <div className="card">
          <div className="card-h"><h3>งานของฉัน</h3><span className="badge badge-maroon">{mine.length} เรื่อง</span></div>
          <div className="card-pad" style={{display:"flex",flexDirection:"column",gap:14}}>
            {mine.map(c=>(
              <div key={c.id} onClick={()=>openCase(c.id)} style={{cursor:"pointer"}}>
                <div className="between"><div className="code sm">{c.id}</div><SLAText s={c.sla}/></div>
                <div className="sm" style={{fontWeight:500,margin:"4px 0 8px"}}>{c.subject}</div>
                <div className="progress"><i style={{width:c.progress+"%"}}></i></div>
                <div className="between tiny muted" style={{marginTop:6}}><span>{STATUS[c.status].label}</span><span>{c.progress}% · ครบกำหนด {thDate(c.due)}</span></div>
              </div>
            ))}
            {mine.length===0 && <div className="muted sm">ไม่มีงานที่มอบหมายให้ท่าน</div>}
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---------------- รายการเรื่องทั้งหมด ---------------- */
function CaseListPage({ cases, officers, openCase, title="จัดการเรื่องร้องเรียน–ร้องทุกข์", sub="เรื่องทั้งหมดในระบบ พร้อมสถานะและการติดตาม SLA", lockTrack }) {
  const [q, setQ] = useState("");
  const [track, setTrack] = useState(lockTrack||"all");
  const [status, setStatus] = useState("all");
  const list = cases.filter(c=>{
    if(track!=="all" && c.track!==track) return false;
    if(status!=="all" && c.status!==status) return false;
    if(q && !(c.subject+c.id+c.reg+c.agency).toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });
  const stChips = [["all","ทั้งหมด"],["received","รับเรื่อง"],["screening","คัดกรอง"],["investigating","สอบสวน"],["reporting","รายงานผล"],["closed","เสร็จสิ้น"]];
  return (
    <div className="fade-in">
      <PageHead title={title} sub={sub}/>
      <div className="card card-pad" style={{marginBottom:18}}>
        <div className="between" style={{gap:14,flexWrap:"wrap"}}>
          <div style={{position:"relative",flex:1,minWidth:240}}>
            <Icon name="search" style={{width:17,height:17,position:"absolute",left:13,top:12,color:"var(--ink-3)"}}/>
            <input className="input" style={{paddingLeft:38}} placeholder="ค้นหารหัส เลขรับ เรื่อง หรือหน่วยงาน..." value={q} onChange={e=>setQ(e.target.value)}/>
          </div>
          {!lockTrack &&
            <div className="seg">
              {[["all","ทุกสาย"],["discipline","ด้านวินัย"],["legal","ด้านกฎหมาย"]].map(([v,l])=>
                <button key={v} className={track===v?"active":""} onClick={()=>setTrack(v)}>{l}</button>)}
            </div>}
        </div>
        <div className="row" style={{gap:8,marginTop:14,flexWrap:"wrap"}}>
          {stChips.map(([v,l])=><button key={v} className={"chip "+(status===v?"active":"")} onClick={()=>setStatus(v)}>{l}</button>)}
        </div>
      </div>

      <div className="card">
        <div className="table-wrap">
          <table className="tbl">
            <thead><tr><th>รหัส / เลขรับ</th><th>เรื่อง</th><th>สายงาน / หมวด</th><th>ผู้รับผิดชอบ</th><th>สถานะ</th><th>SLA</th><th>ครบกำหนด</th></tr></thead>
            <tbody>
              {list.map(c=>{
                const o = officerById(officers, c.assignee);
                return (
                  <tr key={c.id} onClick={()=>openCase(c.id)}>
                    <td><div className="code">{c.id}</div><div className="faint tiny">{c.reg}</div></td>
                    <td style={{maxWidth:260}}>
                      <div style={{fontWeight:500,whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis"}}>{c.subject}</div>
                      <div className="vcenter" style={{gap:6,marginTop:3}}>
                        <PriBadge p={c.priority}/>
                        <span className={"badge "+CLASS[c.cls].c} style={{fontSize:11}}>{CLASS[c.cls].l}</span>
                      </div>
                    </td>
                    <td className="sm"><span className="badge badge-maroon">{TRACKS[c.track].label}</span><div className="faint tiny" style={{marginTop:3}}>{c.cat}</div></td>
                    <td>{o ? <div className="vcenter"><span className="avatar avatar-sm">{o.init}</span><span className="sm">{(o.name||"").split(" ")[0]}</span></div> : <span className="faint sm">ยังไม่มอบหมาย</span>}</td>
                    <td><StatusBadge s={c.status}/></td>
                    <td><SLAText s={c.sla}/></td>
                    <td className="sm muted tnum">{thDate(c.due)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {list.length===0 && <div className="card-pad muted" style={{textAlign:"center"}}>ไม่พบเรื่องที่ตรงกับเงื่อนไข</div>}
      </div>
    </div>
  );
}

/* ---------------- รายละเอียดสำนวน ---------------- */
function CaseDetail({ cid, cases, officers, back, updateCase, role }) {
  const [c, setC] = useState(() => cases.find(x=>x.id===cid) || null);
  const [tab, setTab] = useState("info");
  const [assign, setAssign] = useState(false);
  const [loading, setLoading] = useState(!c || !(c.events));

  useEffect(() => {
    if (!c || !c.events) {
      setLoading(true);
      api.getCase(cid).then(full => { setC(full); setLoading(false); }).catch(() => setLoading(false));
    }
  }, [cid]);

  if (loading) return <LoadingSpinner/>;
  if (!c) return null;
  const o = officerById(officers, c.assignee);
  const canAssign = role==="officer" || role==="dir_legal";

  return (
    <div className="fade-in">
      <button className="btn btn-ghost btn-sm" onClick={back} style={{marginBottom:12}}><Icon name="chevL" style={{width:16,height:16}}/> กลับรายการ</button>

      <div className="between" style={{alignItems:"flex-start",gap:18,flexWrap:"wrap",marginBottom:18}}>
        <div style={{maxWidth:680}}>
          <div className="vcenter" style={{gap:10,marginBottom:8}}>
            <span className="code" style={{fontSize:16}}>{c.id}</span>
            <span className="faint">·</span>
            <span className="muted sm">เลขรับ {c.reg}</span>
            <StatusBadge s={c.status}/>
          </div>
          <h1 style={{fontSize:22,lineHeight:1.45}}>{c.subject}</h1>
          <div className="vcenter" style={{gap:8,marginTop:16,flexWrap:"wrap"}}>
            <PriBadge p={c.priority}/>
            <span className="badge badge-maroon">{TRACKS[c.track].label} · {c.cat}</span>
            <span className={"badge "+CLASS[c.cls].c}><Icon name="lock" style={{width:12,height:12}}/> ชั้น{CLASS[c.cls].l}</span>
            <SLAText s={c.sla}/>
          </div>
        </div>
        <div className="vcenter" style={{gap:8}}>
          {canAssign && c.status!=="closed" &&
            <button className="btn btn-primary" onClick={()=>setAssign(true)}><Icon name="gavel" style={{width:16,height:16}}/> {o?"เปลี่ยนผู้สอบสวน":"แต่งตั้งผู้สอบสวน"}</button>}
          <button className="btn btn-outline"><Icon name="forward" style={{width:16,height:16}}/> เสนอตามลำดับชั้น</button>
        </div>
      </div>

      <div className="grid" style={{gridTemplateColumns:"1.7fr 1fr",alignItems:"start"}}>
        <div className="card">
          <div className="tabs" style={{padding:"0 18px"}}>
            {[["info","รายละเอียด"],["files","คลังสำนวน"],["timeline","ไทม์ไลน์ & SLA"]].map(([v,l])=>
              <button key={v} className={"tab "+(tab===v?"active":"")} onClick={()=>setTab(v)}>{l}</button>)}
          </div>
          <div className="card-pad" style={{padding:24}}>
            {tab==="info" && <>
              <h3 style={{fontSize:15,marginBottom:10}}>เนื้อหาเรื่อง</h3>
              <p className="muted" style={{lineHeight:1.7,fontSize:14.5}}>{c.detail}</p>
              <hr className="hr" style={{margin:"20px 0"}}/>
              <dl className="kv">
                <dt>หน่วยงานเกี่ยวข้อง</dt><dd>{c.agency}</dd>
                <dt>ช่องทางรับเรื่อง</dt><dd>{c.channel}</dd>
                <dt>วันที่รับเรื่อง</dt><dd className="tnum">{thDate(c.received)}</dd>
                <dt>ครบกำหนด (SLA)</dt><dd className="tnum">{thDate(c.due)} <SLAText s={c.sla}/></dd>
              </dl>
            </>}
            {tab==="files" && <div className="grid" style={{gap:10}}>
              <div className="notice notice-info"><Icon name="shieldCheck"/><div>ไฟล์เก็บนอก web root · ตรวจไวรัสแล้ว · ดาวน์โหลดต้องตรวจสิทธิ์ซ้ำและบันทึก Audit</div></div>
              {c.files.map((f,i)=>(
                <div key={i} className="file-row">
                  <Icon name="file" style={{width:18,height:18,color:"var(--maroon)"}}/>
                  <span style={{fontWeight:500}}>{f.n}</span>
                  <span className={"badge "+CLASS[f.c].c} style={{fontSize:11}}>{CLASS[f.c].l}</span>
                  <span className="fmeta" style={{marginLeft:"auto"}}>{f.s}</span>
                  <button className="icon-btn" style={{width:30,height:30}}><Icon name="eye" style={{width:15,height:15}}/></button>
                  <button className="icon-btn" style={{width:30,height:30}}><Icon name="download" style={{width:15,height:15}}/></button>
                </div>
              ))}
            </div>}
            {tab==="timeline" && <div className="timeline">
              {c.events.map((e,i)=>(
                <div key={i} className="tl-item">
                  <div className={"tl-dot "+(e.st==="done"?"done":e.st==="active"?"active":"")}><Icon name={e.ic} style={{width:14,height:14}}/></div>
                  <div className="tl-body">
                    <div className="tt">{e.t}</div>
                    <div className="tm">{e.who} · {e.m}</div>
                    {e.d && <div className="td">{e.d}</div>}
                  </div>
                </div>
              ))}
            </div>}
          </div>
        </div>

        <div className="grid" style={{gap:16}}>
          <div className="card card-pad">
            <h3 style={{fontSize:15,marginBottom:14}}>ผู้รับผิดชอบ</h3>
            {o ? <div className="vcenter" style={{gap:12}}>
                <span className="avatar" style={{width:44,height:44}}>{o.init}</span>
                <div><div style={{fontWeight:600}}>{o.name}</div><div className="muted sm">{o.role}</div><div className="faint tiny">{o.group}</div></div>
              </div>
              : <div className="notice notice-warn"><Icon name="alert"/><div>ยังไม่ได้แต่งตั้งผู้สอบสวน</div></div>}
            {canAssign && c.status!=="closed" &&
              <button className="btn btn-outline btn-block" style={{marginTop:14}} onClick={()=>setAssign(true)}><Icon name="gavel" style={{width:16,height:16}}/> {o?"เปลี่ยนผู้สอบสวน":"แต่งตั้งผู้สอบสวน"}</button>}
          </div>

          <div className="card card-pad">
            <h3 style={{fontSize:15,marginBottom:12}}>ความคืบหน้า</h3>
            <div className="between" style={{marginBottom:8}}><span className="muted sm">{STATUS[c.status].label}</span><span style={{fontWeight:700,fontSize:18}} className="tnum">{c.progress}%</span></div>
            <div className="progress"><i style={{width:c.progress+"%"}}></i></div>
            <div className="muted tiny" style={{marginTop:10}}>ครบกำหนด {thDate(c.due)}</div>
          </div>

          <div className="card card-pad">
            <h3 style={{fontSize:15,marginBottom:12}}>ผู้ร้อง</h3>
            <dl className="kv" style={{gridTemplateColumns:"90px 1fr"}}>
              <dt>ชื่อ</dt><dd>{c.anon? <span className="badge badge-warn"><span className="dot"></span>ไม่ประสงค์ออกนาม</span> : c.complainant}</dd>
              <dt>ติดต่อ</dt><dd className="sm">{c.contact}</dd>
            </dl>
            {c.anon && <div className="notice notice-warn tiny" style={{marginTop:12,padding:"10px 12px"}}><Icon name="lock" style={{width:15,height:15}}/><div>ข้อมูลติดต่อถูกปกปิดจากผู้ถูกร้อง เปิดเผยเฉพาะผู้รับผิดชอบ</div></div>}
          </div>
        </div>
      </div>

      {assign && <AssignModal c={c} officers={officers} close={()=>setAssign(false)} onAssign={(oid)=>{
        updateCase(c.id, {assignee:oid, status:["screening","received","case"].includes(c.status)?"assigned":c.status});
        setAssign(false);
      }}/>}
    </div>
  );
}

function AssignModal({ c, officers, close, onAssign }) {
  const pool = (officers||[]).filter(o=>o.group===TRACKS[c.track].group);
  const [sel, setSel] = useState(c.assignee || (pool[0]&&pool[0].id) || "");
  return (
    <div className="overlay" onClick={close}>
      <div className="modal" onClick={e=>e.stopPropagation()}>
        <div className="modal-h">
          <div className="vcenter"><Icon name="gavel" style={{width:20,height:20,color:"var(--maroon)"}}/><h3 style={{fontSize:17}}>แต่งตั้งผู้สอบสวน / นิติกรเจ้าของเรื่อง</h3></div>
          <button className="icon-btn" onClick={close}><Icon name="x"/></button>
        </div>
        <div className="modal-b">
          <div className="notice notice-info" style={{marginBottom:16}}><Icon name="info"/><div>เรื่องนี้อยู่ในสาย <b>{TRACKS[c.track].label}</b> — แสดงเฉพาะนิติกรใน {TRACKS[c.track].group}</div></div>
          <div className="choices">
            {pool.map(o=>(
              <div key={o.id} className={"choice "+(sel===o.id?"active":"")} onClick={()=>setSel(o.id)}>
                <span className="radio"></span>
                <span className="avatar">{o.init}</span>
                <div style={{flex:1}}>
                  <div className="between"><div className="ct">{o.name}</div><span className="badge">{o.load} เรื่องในมือ</span></div>
                  <div className="cd">{o.role}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
        <div className="modal-f">
          <button className="btn btn-outline" onClick={close}>ยกเลิก</button>
          <button className="btn btn-primary" disabled={!sel} onClick={()=>onAssign(sel)}><Icon name="check" style={{width:16,height:16}}/> ยืนยันการแต่งตั้ง</button>
        </div>
      </div>
    </div>
  );
}

/* ---------------- นำเข้าเรื่องจากเอกสาร ---------------- */
function ImportDocument({ back }) {
  const [d, setD] = useState({ reg:"", subject:"", track:"", cat:"", channel:"หนังสือราชการ", agency:"", priority:"ปกติ", cls:"internal", complainant:"", contact:"", detail:"", files:[] });
  const [done, setDone] = useState(false);
  const set=(k,v)=>setD(s=>({...s,[k]:v}));
  const [saving, setSaving] = useState(false);
  const [saveErr, setSaveErr] = useState("");
  const valid = d.subject.trim() && d.track && d.cat;
  if(done) return (
    <div className="fade-in" style={{maxWidth:560,margin:"40px auto",textAlign:"center"}}>
      <div style={{width:76,height:76,borderRadius:"50%",background:"var(--ok-bg)",display:"grid",placeItems:"center",margin:"0 auto 18px"}}><Icon name="checkCircle" style={{width:38,height:38,color:"var(--ok)"}}/></div>
      <h1 style={{fontSize:24}}>นำเข้าเรื่องเรียบร้อย</h1>
      <p className="muted" style={{marginTop:8}}>ระบบลงทะเบียนและแปลงเป็นสำนวนแล้ว พร้อมเสนอ ผอ.กลุ่มนิติการเพื่อมอบหมาย</p>
      <div className="row" style={{justifyContent:"center",marginTop:22}}>
        <button className="btn btn-outline" onClick={()=>setDone(false)}>นำเข้าเรื่องใหม่</button>
        <button className="btn btn-primary" onClick={back}>กลับแดชบอร์ด</button>
      </div>
    </div>
  );
  return (
    <div className="fade-in" style={{maxWidth:860}}>
      <button className="btn btn-ghost btn-sm" onClick={back} style={{marginBottom:12}}><Icon name="chevL" style={{width:16,height:16}}/> กลับ</button>
      <PageHead title="นำเข้าเรื่องจากเอกสาร" sub="ลงทะเบียนเรื่องที่เข้ามาทางหนังสือราชการ ศูนย์ดำรงธรรม หรือช่องทางอื่น เข้าสู่ระบบ"/>
      <div className="notice notice-info" style={{marginBottom:18}}><Icon name="info"/><div>เมื่อบันทึก ระบบจะออกเลขรับเรื่อง แปลงเป็นสำนวน และเริ่มนับ SLA โดยอัตโนมัติ</div></div>
      <div className="grid" style={{gridTemplateColumns:"1.5fr 1fr",gap:18,alignItems:"start"}}>
        <div className="card card-pad" style={{display:"grid",gap:16}}>
          <div className="field"><label>หัวข้อเรื่อง <span className="req">*</span></label>
            <input className="input" value={d.subject} onChange={e=>set("subject",e.target.value)} placeholder="สรุปเรื่องจากเอกสาร"/></div>
          <div className="grid" style={{gridTemplateColumns:"1fr 1fr",gap:14}}>
            <div className="field"><label>สายงาน <span className="req">*</span></label>
              <select className="select" value={d.track} onChange={e=>{set("track",e.target.value);set("cat","");}}>
                <option value="">— เลือก —</option><option value="discipline">ด้านวินัย</option><option value="legal">ด้านกฎหมาย</option>
              </select></div>
            <div className="field"><label>หมวดหมู่ <span className="req">*</span></label>
              <select className="select" value={d.cat} onChange={e=>set("cat",e.target.value)} disabled={!d.track}>
                <option value="">— เลือก —</option>{d.track&&TRACKS[d.track].cats.map(x=><option key={x}>{x}</option>)}
              </select></div>
          </div>
          <div className="field"><label>หน่วยงาน/สถานศึกษาที่เกี่ยวข้อง</label>
            <input className="input" value={d.agency} onChange={e=>set("agency",e.target.value)} placeholder="เช่น วิทยาลัยเทคนิค..."/></div>
          <div className="field"><label>รายละเอียด</label>
            <textarea className="textarea" value={d.detail} onChange={e=>set("detail",e.target.value)} placeholder="เนื้อหาโดยสรุปจากเอกสาร"/></div>
          <div className="field"><label>แนบสำเนาเอกสาร</label>
            <div className="dropzone" onClick={()=>set("files",[...d.files,{n:"หนังสือราชการ-สแกน.pdf",s:"640 KB"}])}>
              <Icon name="paperclip" style={{width:22,height:22,color:"var(--maroon)",margin:"0 auto 6px"}}/>
              <div style={{fontWeight:600,fontSize:14}}>คลิกเพื่อแนบไฟล์สแกน (จำลอง)</div>
            </div>
            {d.files.map((f,i)=><div key={i} className="file-row" style={{marginTop:8}}><Icon name="file" style={{width:17,height:17,color:"var(--maroon)"}}/><span style={{fontWeight:500}}>{f.n}</span><span className="fmeta">{f.s}</span></div>)}
          </div>
        </div>
        <div className="grid" style={{gap:16}}>
          <div className="card card-pad" style={{display:"grid",gap:14}}>
            <h3 style={{fontSize:15}}>ข้อมูลการลงทะเบียน</h3>
            <div className="field"><label>ช่องทางรับเรื่อง</label>
              <select className="select" value={d.channel} onChange={e=>set("channel",e.target.value)}>
                {CHANNELS.filter(c=>!c.includes("เว็บไซต์")).map(c=><option key={c}>{c}</option>)}
              </select></div>
            <div className="field"><label>ระดับความเร่งด่วน</label>
              <div className="seg" style={{width:"fit-content"}}>
                {["ปกติ","เร่งด่วน","ลับ"].map(p=><button key={p} className={d.priority===p?"active":""} onClick={()=>set("priority",p)}>{p}</button>)}
              </div></div>
            <div className="field"><label>ชั้นความลับ</label>
              <select className="select" value={d.cls} onChange={e=>set("cls",e.target.value)}>
                {Object.entries(CLASS).map(([k,v])=><option key={k} value={k}>{v.l}</option>)}
              </select></div>
          </div>
          <div className="card card-pad" style={{display:"grid",gap:14}}>
            <h3 style={{fontSize:15}}>ผู้ร้อง (ถ้ามี)</h3>
            <div className="field"><label>ชื่อผู้ร้อง</label><input className="input" value={d.complainant} onChange={e=>set("complainant",e.target.value)} placeholder="ระบุ หรือเว้นว่างหากนิรนาม"/></div>
            <div className="field"><label>ช่องทางติดต่อ</label><input className="input" value={d.contact} onChange={e=>set("contact",e.target.value)} placeholder="อีเมล / โทรศัพท์"/></div>
          </div>
          {saveErr && <div className="notice notice-warn"><Icon name="alert"/><div>{saveErr}</div></div>}
          <button className="btn btn-primary btn-lg btn-block" disabled={!valid||saving} onClick={async()=>{
            setSaving(true); setSaveErr("");
            try { await api.createCase({...d, identity:'staff'}); setDone(true); }
            catch(e) { setSaveErr(e.message); }
            finally { setSaving(false); }
          }}><Icon name="hash" style={{width:17,height:17}}/> ลงทะเบียน & แปลงเป็นสำนวน</button>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { PageHead, StatCard, OfficerDashboard, CaseListPage, CaseDetail, AssignModal, ImportDocument });
