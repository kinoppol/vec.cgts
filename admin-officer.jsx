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

/* ---------------- แดชบอร์ดหัวหน้าธุรการ ---------------- */
function HeadSecretaryDashboard({ cases, officers, openCase, setView, onProposed }) {
  const unassigned = cases.filter(c => !c.assignee && !['closed','rejected'].includes(c.status));
  const [propModal, setPropModal] = useState(null); // { case }

  return (
    <div className="fade-in">
      <PageHead title="แดชบอร์ดหัวหน้าธุรการ" sub="สำนวนที่ยังไม่ได้รับการมอบหมาย — นำเสนอผู้อำนวยการสำนักนิติการ">
        <button className="btn btn-outline" onClick={()=>setView("cases")}><Icon name="inbox" style={{width:16,height:16}}/> ดูสำนวนทั้งหมด</button>
      </PageHead>

      <div className="grid" style={{gridTemplateColumns:"repeat(3,1fr)",marginBottom:22}}>
        <StatCard ic="inbox" lbl="รอมอบหมาย" num={unassigned.length} sub="ต้องนำเสนอผู้อำนวยการ" tone="warn"/>
        <StatCard ic="alert" lbl="เร่งด่วน" num={unassigned.filter(c=>c.priority==='เร่งด่วน').length} sub="ต้องดำเนินการก่อน" tone="danger"/>
        <StatCard ic="clock" lbl="รับเรื่องวันนี้" num={unassigned.filter(c=>c.received===new Date().toISOString().slice(0,10)).length} sub="เรื่องที่เข้ามาวันนี้" tone="info"/>
      </div>

      <div className="card">
        <div className="card-h">
          <h3>สำนวนรอมอบหมาย</h3>
          <span className="badge badge-warn">{unassigned.length} เรื่อง</span>
        </div>
        <div className="table-wrap">
          <table className="tbl">
            <thead><tr><th>รหัส/เลขรับ</th><th>เรื่อง</th><th>สายงาน</th><th>สถานะ</th><th>วันที่รับ</th><th>ความสำคัญ</th><th></th></tr></thead>
            <tbody>
              {unassigned.map(c=>(
                <tr key={c.id}>
                  <td onClick={()=>openCase(c.id)} style={{cursor:'pointer'}}>
                    <div className="code">{c.id}</div>
                    <div className="faint tiny">{c.reg!=="—"?c.reg:"ยังไม่ลงทะเบียน"}</div>
                  </td>
                  <td style={{maxWidth:240}} onClick={()=>openCase(c.id)} style={{cursor:'pointer'}}>
                    <div style={{fontWeight:500,whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis"}}>{c.subject}</div>
                    {c.anon && <span className="badge badge-warn" style={{marginTop:4,fontSize:11}}>ไม่ประสงค์ออกนาม</span>}
                  </td>
                  <td className="sm"><span className="badge badge-maroon">{TRACKS[c.track]?.label}</span></td>
                  <td><StatusBadge s={c.status}/></td>
                  <td className="sm muted tnum">{thDate(c.received)}</td>
                  <td><PriBadge p={c.priority}/></td>
                  <td>
                    <button className="btn btn-primary btn-sm" onClick={()=>setPropModal({case:c})}>
                      <Icon name="flag" style={{width:14,height:14}}/> นำเสนอ
                    </button>
                  </td>
                </tr>
              ))}
              {unassigned.length===0 && (
                <tr><td colSpan={7} style={{textAlign:"center",padding:"32px 0",color:"var(--ink-3)"}}>ไม่มีสำนวนรอมอบหมาย</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {propModal && (
        <ProposeModal case_={propModal.case} officers={officers}
          onClose={()=>setPropModal(null)}
          onSaved={()=>{ setPropModal(null); onProposed && onProposed(); }}/>
      )}
    </div>
  );
}

/* ---------------- Modal นำเสนอมอบหมาย (head_secretary) ---------------- */
function ProposeModal({ case_, officers, onClose, onSaved }) {
  const [groups, setGroups]           = useState([]);
  const [selGroups, setSelGroups]     = useState([]);
  const [selPersonnel, setSelPersonnel] = useState([]); // officer ids
  const NOTE_PREFIX = 'เรียน ผู้อำนวยการสำนักนิติการ\n';
  const [note, setNote]   = useState(NOTE_PREFIX);
  const [saving, setSaving] = useState(false);
  const [err, setErr]       = useState('');

  useEffect(() => {
    api.getLookups('group_name').then(setGroups).catch(() => {});
  }, []);

  const toggleGroup = (name) =>
    setSelGroups(prev => prev.includes(name) ? prev.filter(g => g !== name) : [...prev, name]);

  const togglePersonnel = (id) =>
    setSelPersonnel(prev => prev.includes(id) ? prev.filter(p => p !== id) : [...prev, id]);

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true); setErr('');
    try {
      await api.proposeAssign({
        case_id: case_.id,
        proposed_groups:    selGroups.length    ? selGroups    : null,
        proposed_personnel: selPersonnel.length ? selPersonnel : null,
        note: note.trim() || null,
      });
      onSaved();
    } catch(e) {
      setErr(e.message);
    }
    setSaving(false);
  };

  const checkboxRow = (checked, onToggle, label) => (
    <label style={{display:'flex',alignItems:'center',gap:10,padding:'7px 12px',borderRadius:8,cursor:'pointer',
      background: checked ? 'var(--maroon-50,rgba(120,20,30,.08))' : 'var(--surface-2)',
      border: checked ? '1.5px solid var(--maroon)' : '1.5px solid transparent',
      transition:'background .12s,border .12s'}}>
      <input type="checkbox" checked={checked} onChange={onToggle}
        style={{width:15,height:15,accentColor:'var(--maroon)',flexShrink:0}}/>
      <span style={{fontSize:14,fontWeight: checked ? 600 : 400}}>{label}</span>
    </label>
  );

  return (
    <div style={{position:'fixed',inset:0,background:'rgba(20,10,12,.55)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:200,padding:24}} onClick={onClose}>
      <div style={{background:'var(--surface)',borderRadius:12,boxShadow:'0 8px 40px rgba(0,0,0,.35)',width:'100%',maxWidth:520,maxHeight:'90vh',display:'flex',flexDirection:'column'}} onClick={e=>e.stopPropagation()}>
        <div style={{padding:'18px 24px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between',flexShrink:0}}>
          <h3 style={{margin:0,fontSize:16}}>นำเสนอมอบหมายสำนวน</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <form onSubmit={submit} style={{padding:'20px 24px',display:'flex',flexDirection:'column',gap:16,overflowY:'auto',flex:1}}>
          <div className="notice notice-warn" style={{fontSize:13}}>
            <Icon name="flag"/><div><b>{case_.id}</b> — {case_.subject}</div>
          </div>
          {err && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}

          {/* กลุ่มงาน */}
          <div className="field">
            <label>เสนอมอบหมายให้กลุ่มงาน <span style={{color:'var(--ink-3)',fontWeight:400}}>(เลือกได้หลายกลุ่ม)</span></label>
            {groups.length === 0
              ? <div className="faint sm" style={{padding:'8px 0'}}>ไม่พบรายการกลุ่มงาน</div>
              : <div style={{display:'flex',flexDirection:'column',gap:6,marginTop:4}}>
                  {groups.map(g => checkboxRow(selGroups.includes(g.name), ()=>toggleGroup(g.name), g.name))}
                </div>
            }
          </div>

          {/* บุคลากรที่เกี่ยวข้อง */}
          <div className="field">
            <label>บุคลากรที่เกี่ยวข้อง <span style={{color:'var(--ink-3)',fontWeight:400}}>(ไม่บังคับ)</span></label>
            {officers && officers.filter(o=>o.active).length > 0
              ? <div style={{display:'flex',flexDirection:'column',gap:6,marginTop:4}}>
                  {officers.filter(o=>o.active).map(o =>
                    checkboxRow(
                      selPersonnel.includes(o.id),
                      ()=>togglePersonnel(o.id),
                      <span>{o.name}{o.job_title && <span className="faint" style={{fontSize:12}}> · {o.job_title}</span>}</span>
                    )
                  )}
                </div>
              : <div className="faint sm" style={{padding:'8px 0'}}>ไม่พบรายการบุคลากร</div>
            }
          </div>

          {/* หมายเหตุ */}
          <div className="field">
            <label>หมายเหตุถึงผู้อำนวยการ</label>
            <textarea className="input" rows={5} value={note} onChange={e=>setNote(e.target.value)}
              style={{fontFamily:'inherit',lineHeight:1.7}}/>
          </div>

          <div style={{display:'flex',gap:10,justifyContent:'flex-end',flexShrink:0}}>
            <button type="button" className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? <LoadingSpinner/> : <><Icon name="flag" style={{width:14,height:14}}/> ส่งนำเสนอ</>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

/* ---------------- หน้าข้อเสนอรอพิจารณา (dir_legal) ---------------- */
function AssignProposalsPage({ proposals, officers, onApproved }) {
  const [modal, setModal] = useState(null); // proposal object

  if (proposals.length === 0) {
    return (
      <div className="fade-in">
        <PageHead title="ข้อเสนอรอพิจารณา" sub="ข้อเสนอมอบหมายสำนวนจากหัวหน้าธุรการ"/>
        <div className="card card-pad" style={{textAlign:'center',color:'var(--ink-3)'}}>ไม่มีข้อเสนอที่รอพิจารณา</div>
      </div>
    );
  }

  return (
    <div className="fade-in">
      <PageHead title="ข้อเสนอรอพิจารณา" sub="ข้อเสนอมอบหมายสำนวนจากหัวหน้าธุรการ">
        <span className="badge badge-warn">{proposals.length} รายการ</span>
      </PageHead>
      <div className="card">
        <div className="table-wrap">
          <table className="tbl">
            <thead><tr><th>สำนวน</th><th>เรื่อง</th><th>เสนอโดย</th><th>กลุ่มงานที่เสนอ</th><th>หมายเหตุ</th><th>วันที่เสนอ</th><th></th></tr></thead>
            <tbody>
              {proposals.map(p=>{
                const grps = (() => { try { return p.proposed_groups ? JSON.parse(p.proposed_groups) : []; } catch { return []; } })();
                return (
                  <tr key={p.id}>
                    <td><div className="code sm">{p.case_id}</div></td>
                    <td style={{maxWidth:220}}><div style={{fontWeight:500,whiteSpace:'nowrap',overflow:'hidden',textOverflow:'ellipsis'}}>{p.case_subject}</div></td>
                    <td className="sm">{p.proposed_by_name}</td>
                    <td>{grps.length
                      ? <div style={{display:'flex',flexWrap:'wrap',gap:4}}>{grps.map((g,i)=><span key={i} className="badge badge-info" style={{fontSize:11}}>{g}</span>)}</div>
                      : <span className="faint sm">ไม่ระบุ</span>}
                    </td>
                    <td className="sm muted" style={{maxWidth:200,whiteSpace:'pre-wrap'}}>{p.propose_note || '—'}</td>
                    <td className="sm muted tnum">{thDate(p.created_at?.slice(0,10))}</td>
                    <td>
                      <button className="btn btn-primary btn-sm" onClick={()=>setModal(p)}>
                        <Icon name="gavel" style={{width:14,height:14}}/> อนุมัติ/แก้ไข
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
      {modal && (
        <ApproveProposalModal proposal={modal} officers={officers}
          onClose={()=>setModal(null)}
          onApproved={(caseId)=>{ setModal(null); onApproved && onApproved(caseId); }}/>
      )}
    </div>
  );
}

/* ---------------- Modal อนุมัติ/แก้ไขข้อเสนอ (dir_legal) ---------------- */
function ApproveProposalModal({ proposal, officers, onClose, onApproved }) {
  const [officerId, setOfficerId] = useState(proposal.proposed_officer || '');
  const [note, setNote]           = useState('');
  const [saving, setSaving]       = useState(false);
  const [err, setErr]             = useState('');

  const proposedGroups = (() => { try { return proposal.proposed_groups ? JSON.parse(proposal.proposed_groups) : []; } catch { return []; } })();
  // แบ่งนิติกรตามกลุ่มงานที่เสนอ (ถ้ามี)
  const inGroups    = proposedGroups.length ? officers.filter(o => o.active && proposedGroups.includes(o.group_name)) : [];
  const otherOfficers = officers.filter(o => o.active && !inGroups.includes(o));

  const submit = async (action) => {
    if (action === 'approve' && !officerId) { setErr('กรุณาเลือกนิติกรก่อนอนุมัติ'); return; }
    setSaving(true); setErr('');
    try {
      await api.approveAssign(proposal.id, { action, final_officer: officerId, review_note: note });
      onApproved(proposal.case_id);
    } catch(e) {
      setErr(e.message);
    }
    setSaving(false);
  };

  return (
    <div style={{position:'fixed',inset:0,background:'rgba(20,10,12,.55)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:200,padding:24}} onClick={onClose}>
      <div style={{background:'var(--surface)',borderRadius:12,boxShadow:'0 8px 40px rgba(0,0,0,.35)',width:'100%',maxWidth:480}} onClick={e=>e.stopPropagation()}>
        <div style={{padding:'18px 24px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between'}}>
          <h3 style={{margin:0,fontSize:16}}>พิจารณาข้อเสนอมอบหมาย</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <div style={{padding:'20px 24px',display:'flex',flexDirection:'column',gap:14}}>
          <div className="notice notice-info" style={{fontSize:13}}>
            <Icon name="inbox"/><div>สำนวน <b>{proposal.case_id}</b> — {proposal.case_subject}</div>
          </div>
          <div className="faint sm">เสนอโดย: <b>{proposal.proposed_by_name}</b></div>
          {(() => {
            const grps = (() => { try { return proposal.proposed_groups ? JSON.parse(proposal.proposed_groups) : []; } catch { return []; } })();
            if (!grps.length) return null;
            return (
              <div style={{background:'var(--surface-2)',borderRadius:8,padding:'10px 14px'}}>
                <div style={{fontSize:12,color:'var(--ink-3)',marginBottom:6}}>กลุ่มงานที่เสนอ:</div>
                <div style={{display:'flex',flexWrap:'wrap',gap:6}}>
                  {grps.map((g,i)=><span key={i} className="badge badge-info">{g}</span>)}
                </div>
              </div>
            );
          })()}
          {(() => {
            const pers = (() => { try { return proposal.proposed_personnel ? JSON.parse(proposal.proposed_personnel) : []; } catch { return []; } })();
            if (!pers.length) return null;
            return (
              <div style={{background:'var(--surface-2)',borderRadius:8,padding:'10px 14px'}}>
                <div style={{fontSize:12,color:'var(--ink-3)',marginBottom:6}}>บุคลากรที่เกี่ยวข้อง:</div>
                <div style={{display:'flex',flexWrap:'wrap',gap:6}}>
                  {pers.map(pid => {
                    const o = officerById(officers, pid);
                    return o
                      ? <div key={pid} className="vcenter" style={{gap:6,background:'var(--surface)',borderRadius:6,padding:'3px 10px',border:'1px solid var(--line)'}}>
                          <span className="avatar avatar-sm" style={{width:22,height:22,fontSize:10}}>{o.init}</span>
                          <span style={{fontSize:13}}>{o.name}</span>
                        </div>
                      : <span key={pid} className="faint sm">{pid}</span>;
                  })}
                </div>
              </div>
            );
          })()}
          {proposal.propose_note && <div style={{background:'var(--surface-2)',borderRadius:8,padding:'10px 14px',fontSize:13}}>
            <b style={{fontSize:12,color:'var(--ink-3)'}}>หมายเหตุจากหัวหน้าธุรการ:</b>
            <pre style={{margin:'6px 0 0',fontFamily:'inherit',whiteSpace:'pre-wrap',fontSize:13,lineHeight:1.6}}>{proposal.propose_note}</pre>
          </div>}
          {err && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}
          <div className="field">
            <label>มอบหมายให้นิติกร <span className="req">*</span></label>
            <select className="input" value={officerId} onChange={e=>setOfficerId(e.target.value)}>
              <option value="">— เลือกนิติกร —</option>
              {inGroups.length > 0 && <>
                <optgroup label="กลุ่มงานที่เสนอ">
                  {inGroups.map(o=><option key={o.id} value={o.id}>{o.name}{o.job_title ? ` · ${o.job_title}` : ''}</option>)}
                </optgroup>
                {otherOfficers.length > 0 &&
                  <optgroup label="นิติกรอื่น">
                    {otherOfficers.map(o=><option key={o.id} value={o.id}>{o.name}{o.job_title ? ` · ${o.job_title}` : ''}</option>)}
                  </optgroup>}
              </>}
              {inGroups.length === 0 && otherOfficers.map(o=>(
                <option key={o.id} value={o.id}>{o.name}{o.job_title ? ` · ${o.job_title}` : ''}</option>
              ))}
            </select>
          </div>
          <div className="field">
            <label>หมายเหตุ (ไม่บังคับ)</label>
            <textarea className="input" rows={2} value={note} onChange={e=>setNote(e.target.value)}/>
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',marginTop:4}}>
            <button type="button" className="btn btn-ghost" onClick={onClose} disabled={saving}>ยกเลิก</button>
            <button type="button" className="btn btn-outline" onClick={()=>submit('change')} disabled={saving}>
              <Icon name="edit" style={{width:14,height:14}}/> เปลี่ยนและมอบหมาย
            </button>
            <button type="button" className="btn btn-primary" onClick={()=>submit('approve')} disabled={saving}>
              {saving ? <LoadingSpinner/> : <><Icon name="gavel" style={{width:14,height:14}}/> อนุมัติ</>}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---------------- รายการเรื่องทั้งหมด ---------------- */
function CaseListPage({ cases, officers, openCase, title="จัดการเรื่องร้องเรียน–ร้องทุกข์", sub="เรื่องทั้งหมดในระบบ พร้อมสถานะและการติดตาม SLA", lockTrack }) {
  const [q, setQ]           = useState("");
  const [matchMode, setMatchMode] = useState("any"); // "any" = บางคำ, "all" = ทุกคำ
  const [scope, setScope]   = useState("all");       // "all" | "subject" | "track" | "officer" | "number"
  const [track, setTrack]   = useState(lockTrack||"all");
  const [status, setStatus] = useState("all");

  const keywords = q.trim().toLowerCase().split(/\s+/).filter(Boolean);

  const caseText = (c, sc) => {
    const o = officerById(officers, c.assignee);
    switch(sc) {
      case "subject":  return (c.subject + " " + c.agency + " " + (c.detail||"")).toLowerCase();
      case "track":    return ((TRACKS[c.track]?.label||"") + " " + (c.cat||"")).toLowerCase();
      case "officer":  return (o?.name||"").toLowerCase();
      case "number":   return (c.id + " " + c.reg).toLowerCase();
      default:         return (c.subject + " " + c.id + " " + c.reg + " " + c.agency + " " + (c.detail||"") + " " + (TRACKS[c.track]?.label||"") + " " + (o?.name||"")).toLowerCase();
    }
  };

  const list = cases.filter(c=>{
    if(track!=="all" && c.track!==track) return false;
    if(status!=="all" && c.status!==status) return false;
    if(keywords.length > 0) {
      const text = caseText(c, scope);
      const match = matchMode === "all"
        ? keywords.every(k => text.includes(k))
        : keywords.some(k => text.includes(k));
      if(!match) return false;
    }
    return true;
  });

  const stChips = [["all","ทั้งหมด"],["received","รับเรื่อง"],["screening","คัดกรอง"],["investigating","สอบสวน"],["reporting","รายงานผล"],["closed","เสร็จสิ้น"]];
  const scopeOpts = [["all","ทุกฟิลด์"],["subject","เรื่อง/หน่วยงาน"],["number","รหัส/เลขรับ"],["officer","ผู้รับผิดชอบ"],["track","สายงาน"]];

  return (
    <div className="fade-in">
      <PageHead title={title} sub={sub}/>
      <div className="card card-pad" style={{marginBottom:18}}>
        {/* แถวค้นหา */}
        <div className="between" style={{gap:10,flexWrap:"wrap",alignItems:"flex-start"}}>
          <div style={{position:"relative",flex:1,minWidth:260}}>
            <Icon name="search" style={{width:17,height:17,position:"absolute",left:13,top:12,color:"var(--ink-3)"}}/>
            <input className="input" style={{paddingLeft:38}}
              placeholder="พิมพ์คำค้น คั่นด้วยช่องว่าง เช่น  ละเมิด เชียงใหม่"
              value={q} onChange={e=>setQ(e.target.value)}/>
          </div>
          <div style={{display:"flex",gap:8,flexWrap:"wrap",alignItems:"center"}}>
            <div className="seg">
              <button className={matchMode==="any"?"active":""} onClick={()=>setMatchMode("any")} title="แสดงผลที่ตรงกับคำใดคำหนึ่ง">บางคำ (OR)</button>
              <button className={matchMode==="all"?"active":""} onClick={()=>setMatchMode("all")} title="แสดงผลที่ตรงกับทุกคำ">ทุกคำ (AND)</button>
            </div>
            <select className="input" style={{width:"auto",minWidth:150}} value={scope} onChange={e=>setScope(e.target.value)}>
              {scopeOpts.map(([v,l])=><option key={v} value={v}>{l}</option>)}
            </select>
            {!lockTrack &&
              <div className="seg">
                {[["all","ทุกสาย"],["discipline","ด้านวินัย"],["legal","ด้านกฎหมาย"],["general","บริหารงานทั่วไป"]].map(([v,l])=>
                  <button key={v} className={track===v?"active":""} onClick={()=>setTrack(v)}>{l}</button>)}
              </div>}
          </div>
        </div>
        {/* แท็กคำที่กำลังค้น */}
        {keywords.length > 0 && (
          <div className="vcenter" style={{gap:6,marginTop:10,flexWrap:"wrap"}}>
            <span className="faint tiny">คำค้น:</span>
            {keywords.map((k,i)=>(
              <span key={i} style={{display:"inline-flex",alignItems:"center",gap:4,background:"var(--accent-bg,rgba(120,20,30,.12))",color:"var(--accent)",borderRadius:4,padding:"2px 8px",fontSize:12,fontWeight:500}}>
                {k}
              </span>
            ))}
            <span className="faint tiny">— พบ {list.length} เรื่อง ({matchMode==="all"?"ต้องมีทุกคำ":"มีคำใดคำหนึ่ง"})</span>
            <button className="chip" style={{fontSize:11,padding:"1px 8px"}} onClick={()=>setQ("")}>ล้าง</button>
          </div>
        )}
        {/* กรองสถานะ */}
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
                        <span className={"badge "+(CLASS[c.cls]||CLASS.public).c} style={{fontSize:11}}>{(CLASS[c.cls]||CLASS.public).l}</span>
                      </div>
                    </td>
                    <td className="sm"><span className="badge badge-maroon">{TRACKS[c.track]?.label||c.track}</span><div className="faint tiny" style={{marginTop:3}}>{c.cat}</div></td>
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

/* ---- PdfModal — แสดง PDF ใน modal ขนาดใหญ่ ---- */
function PdfModal({ url, filename, onClose }) {
  React.useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const downloadUrl = url.replace('&inline=1','').replace('?inline=1','');

  return (
    <>
      {/* backdrop + iframe container — zIndex เหนือ banner (9999) */}
      <div style={{
        position:'fixed', inset:0, background:'#1a0a0e',
        zIndex:10000, display:'flex', flexDirection:'column',
      }}>
        {/* toolbar — fixed ที่ระดับเดียวกับ modal แต่อยู่บนสุด */}
        <div style={{
          display:'flex', alignItems:'center', gap:12, flexShrink:0,
          padding:'10px 16px', background:'#2d1217',
          borderBottom:'1px solid rgba(255,255,255,.12)',
        }}>
          <Icon name="file" style={{width:16,height:16,color:'#f87171',flexShrink:0}}/>
          <span style={{flex:1,fontSize:13,color:'#fff',overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}}>
            {filename}
          </span>
          <a href={downloadUrl} download={filename}
             style={{
               color:'#93c5fd', fontSize:12, textDecoration:'none',
               display:'flex', alignItems:'center', gap:4,
               padding:'4px 10px', borderRadius:6,
               background:'rgba(147,197,253,.12)', border:'1px solid rgba(147,197,253,.3)',
             }}>
            <Icon name="download" style={{width:13,height:13}}/> ดาวน์โหลด
          </a>
          <button onClick={onClose} style={{
            background:'var(--maroon)', border:'none', color:'#fff',
            borderRadius:6, padding:'5px 16px', cursor:'pointer',
            fontSize:13, fontWeight:700, letterSpacing:'.5px',
          }}>✕ ปิด</button>
        </div>
        {/* iframe */}
        <iframe
          src={url}
          style={{flex:1, width:'100%', border:'none', display:'block'}}
          title={filename}
        />
      </div>
    </>
  );
}

/* ---- modal ยืนยันการเสร็จสิ้นขั้นตอน (ต้องมี note หรือ PDF) ---- */
function StepDoneModal({ step, onConfirm, onClose }) {
  const [note,    setNote]    = React.useState('');
  const [file,    setFile]    = React.useState(null);
  const [busy,    setBusy]    = React.useState(false);
  const [err,     setErr]     = React.useState('');
  const fileRef = React.useRef(null);

  const valid = note.trim() !== '' || file !== null;

  async function submit(e) {
    e.preventDefault();
    if (!valid) { setErr('กรุณาพิมพ์บันทึก หรือแนบไฟล์ PDF อย่างน้อยหนึ่งอย่าง'); return; }
    setBusy(true); setErr('');
    try {
      await onConfirm({ note: note.trim(), file });
      onClose();
    } catch(ex) { setErr(ex.message); }
    setBusy(false);
  }

  return (
    <div style={{position:'fixed',inset:0,background:'rgba(20,10,12,.55)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:300,padding:24}}
         onClick={onClose}>
      <div style={{background:'var(--surface)',borderRadius:12,width:'100%',maxWidth:480,boxShadow:'0 8px 40px rgba(0,0,0,.35)'}}
           onClick={e=>e.stopPropagation()}>
        <div style={{padding:'16px 20px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between'}}>
          <h3 style={{margin:0,fontSize:16}}>บันทึกการดำเนินการ — {step.label}</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <form onSubmit={submit} style={{padding:'16px 20px',display:'flex',flexDirection:'column',gap:14}}>
          <div>
            <label style={{fontSize:13,fontWeight:600,display:'block',marginBottom:6}}>
              บันทึกการดำเนินการ
            </label>
            <textarea
              className="input"
              rows={4}
              placeholder="พิมพ์สรุปผลการดำเนินการ เช่น รับเรื่องแล้ว ส่งต่อให้ผู้รับผิดชอบ ..."
              value={note}
              onChange={e=>{ setNote(e.target.value); setErr(''); }}
              style={{width:'100%',resize:'vertical',fontSize:13}}
            />
          </div>
          <div>
            <label style={{fontSize:13,fontWeight:600,display:'block',marginBottom:6}}>
              แนบไฟล์ PDF <span style={{color:'var(--ink-3)',fontWeight:400}}>(ไม่เกิน 20 MB)</span>
            </label>
            {file ? (
              <div className="vcenter" style={{gap:8,padding:'8px 12px',background:'var(--surface-2)',borderRadius:8,fontSize:13}}>
                <Icon name="file" style={{width:16,height:16,color:'var(--danger)'}}/>
                <span style={{flex:1,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}}>{file.name}</span>
                <span style={{color:'var(--ink-3)',fontSize:11}}>{(file.size/1024).toFixed(0)} KB</span>
                <button type="button" className="icon-btn" onClick={()=>{ setFile(null); fileRef.current.value=''; }}>
                  <Icon name="x" style={{width:14,height:14}}/>
                </button>
              </div>
            ) : (
              <button type="button" className="btn btn-ghost btn-sm"
                style={{width:'100%',border:'1.5px dashed var(--border)',padding:'10px',fontSize:13}}
                onClick={()=>fileRef.current?.click()}>
                <Icon name="upload" style={{width:15,height:15}}/> เลือกไฟล์ PDF
              </button>
            )}
            <input ref={fileRef} type="file" accept=".pdf,application/pdf" style={{display:'none'}}
              onChange={e=>{ const f=e.target.files[0]; if(f){ setFile(f); setErr(''); } }}/>
          </div>
          {err && <div className="notice notice-danger" style={{margin:0,padding:'8px 12px',fontSize:13}}>{err}</div>}
          <div style={{display:'flex',gap:8,justifyContent:'flex-end',paddingTop:4}}>
            <button type="button" className="btn btn-ghost btn-sm" onClick={onClose} disabled={busy}>ยกเลิก</button>
            <button type="submit" className="btn btn-primary btn-sm" disabled={busy || !valid}>
              {busy ? '…กำลังบันทึก' : '✓ ยืนยันเสร็จสิ้น'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

/* ---------------- CaseTimeline — เส้นเวลาการดำเนินการ ---------------- */
function CaseTimeline({ steps = [], onRefresh, canEdit }) {
  const [busy,      setBusy]      = React.useState(null);
  const [editing,   setEditing]   = React.useState(null);
  const [doneModal, setDoneModal] = React.useState(null);
  const [pdfModal,  setPdfModal]  = React.useState(null); // {url, filename}

  const C = { g:'var(--ok)', a:'var(--warn)', r:'var(--danger)' };

  function thShortDate(iso) {
    if (!iso) return null;
    const d = new Date(iso);
    return d.toLocaleDateString('th-TH', { day:'numeric', month:'short' });
  }

  async function ensureEvent(s, ev_status) {
    if (s.event_id) return s.event_id;
    const created = await api.createEvent({ case_id: s._case_id, step_key: s.step_key, ev_status });
    return created.id;
  }

  async function markStatus(s, st) {
    if (busy) return;
    // กด "เสร็จแล้ว" → เปิด modal ขอ note/file ก่อน
    if (st === 'done') { setDoneModal(s); return; }
    setBusy(s.step_key + ':' + st);
    try {
      if (s.event_id) {
        await api.updateEvent(s.event_id, { ev_status: st });
      } else {
        await api.createEvent({ case_id: s._case_id, step_key: s.step_key, ev_status: st });
      }
      await onRefresh();
    } catch(e) { alert(e.message); }
    setBusy(null);
  }

  async function confirmDone(s, { note, file }) {
    // 1. เตรียม event_id (สร้างถ้าไม่มี)
    let eid = s.event_id;
    if (!eid) {
      const created = await api.createEvent({ case_id: s._case_id, step_key: s.step_key, ev_status: 'active' });
      eid = created.id;
    }
    // 2. อัปโหลดไฟล์ก่อน (ถ้ามี)
    let attachFields = {};
    if (file) {
      const uploaded = await api.uploadEventFile(eid, file);
      attachFields = {
        attachment_name: uploaded.attachment_name,
        attachment_path: uploaded.attachment_path,
        attachment_size: uploaded.attachment_size,
        _has_file: true,
      };
    }
    // 3. mark done พร้อม detail + attachment info
    await api.updateEvent(eid, {
      ev_status: 'done',
      detail: note || null,
      ...attachFields,
    });
    await onRefresh();
  }

  async function saveDate(s, field, value) {
    if (busy) return;
    setBusy(s.step_key + ':' + field);
    try {
      const eid = await ensureEvent(s, 'active');
      await api.updateEvent(eid, { [field]: value || null });
      await onRefresh();
    } catch(e) { alert(e.message); }
    setBusy(null);
    setEditing(null);
  }

  function InlineDatePicker({ s, field }) {
    const val  = s[field] || '';
    const isMe = editing?.key === s.step_key && editing?.field === field;
    const eid  = `idf-${s.step_key}-${field}`;
    if (isMe) return (
      <span className="vcenter" style={{gap:4}}>
        <input type="date" className="input" id={eid}
          style={{padding:'2px 7px',fontSize:12,width:136}}
          defaultValue={val || new Date().toISOString().slice(0,10)}
          onKeyDown={e2=>{
            if(e2.key==='Enter') saveDate(s, field, e2.target.value);
            if(e2.key==='Escape') setEditing(null);
          }} autoFocus/>
        <button className="btn btn-sm btn-primary" style={{padding:'2px 8px',fontSize:11}}
          onClick={()=>{ const el=document.getElementById(eid); if(el) saveDate(s,field,el.value); }}>
          ตกลง</button>
        <button className="btn btn-sm btn-ghost" style={{padding:'2px 6px',fontSize:11}}
          onClick={()=>setEditing(null)}>✕</button>
      </span>
    );
    return (
      <button className="btn btn-ghost btn-sm"
        style={{fontSize:11,padding:'1px 6px',gap:3,color:val?'var(--ink)':'var(--ink-3)'}}
        onClick={()=>setEditing({ key:s.step_key, field })}>
        <Icon name="calendar" style={{width:10,height:10}}/>
        {val ? thShortDate(val) : 'บันทึกวัน'}
      </button>
    );
  }

  if (!steps.length) return (
    <div className="muted sm" style={{padding:16}}>ยังไม่มีข้อมูลขั้นตอน</div>
  );

  const timeline = (
    <div style={{padding:'4px 0'}}>
      {steps.map((s, i) => {
        const isDone    = s.ev_status === 'done';
        const isActive  = s.ev_status === 'active';
        const isPending = !isDone && !isActive;
        const color     = s.step_sla ? C[s.step_sla] : 'var(--ink-3)';
        const isLast    = i === steps.length - 1;

        /* แถบกรอบเวลา */
        const pct = s.days_used !== null
          ? Math.min(100, Math.round(s.days_used / s.days_allowed * 100))
          : 0;

        return (
          <div key={s.step_key} style={{display:'flex',gap:0,position:'relative'}}>
            {/* ── คอลัมน์วันที่ ── */}
            <div style={{width:72,flexShrink:0,textAlign:'right',paddingRight:12,paddingTop:2}}>
              {isDone && s.completed_at
                ? <span className="tnum" style={{fontSize:12,color:'var(--ok)',fontWeight:600}}>{thShortDate(s.completed_at)}</span>
                : isActive && s.started_at
                ? <span className="tnum" style={{fontSize:12,color:'var(--info)',fontWeight:600}}>{thShortDate(s.started_at)}</span>
                : <span style={{fontSize:11,color:'var(--ink-3)'}}>—</span>}
            </div>

            {/* ── dot + เส้นแนวตั้ง ── */}
            <div style={{display:'flex',flexDirection:'column',alignItems:'center',flexShrink:0,width:28}}>
              <div style={{
                width:20,height:20,borderRadius:'50%',flexShrink:0,zIndex:1,
                display:'grid',placeItems:'center',
                background: isDone ? 'var(--ok)' : isActive ? 'var(--info)' : 'var(--surface-2)',
                border: `2px solid ${isDone ? 'var(--ok)' : isActive ? 'var(--info)' : 'var(--border)'}`,
                transition:'all .2s',
              }}>
                {(isDone || isActive)
                  ? <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3" strokeLinecap="round"><path d="M20 6L9 17l-5-5"/></svg>
                  : <div style={{width:6,height:6,borderRadius:'50%',background:'var(--border)'}}/>}
              </div>
              {!isLast && (
                <div style={{
                  width:2,flex:1,minHeight:16,
                  background: isDone ? 'var(--ok)' : 'var(--border)',
                  opacity: isDone ? 0.5 : 0.4,
                }}/>
              )}
            </div>

            {/* ── เนื้อหา ── */}
            <div style={{flex:1,paddingLeft:12,paddingBottom: isLast ? 0 : 16,paddingTop:1}}>
              {/* แถว 1: ชื่อขั้นตอน + badge */}
              <div className="between" style={{alignItems:'center',gap:8,flexWrap:'wrap'}}>
                <span style={{
                  fontWeight: isActive ? 700 : isDone ? 500 : 400,
                  fontSize:14,
                  color: isPending ? 'var(--ink-3)' : 'var(--ink)',
                }}>
                  {s.label}
                </span>

                <div className="vcenter" style={{gap:6}}>
                  {/* กรอบเวลา */}
                  <span style={{fontSize:11,color:'var(--ink-3)'}}>
                    {s.days_allowed} วัน
                  </span>

                  {/* badge สถานะ SLA */}
                  {isDone && s.step_sla && (
                    <span style={{fontSize:11,fontWeight:600,
                      color:C[s.step_sla],
                      background:`color-mix(in srgb,${C[s.step_sla]} 12%,transparent)`,
                      padding:'1px 8px',borderRadius:10}}>
                      {s.step_sla==='g' ? '✓ ทันกำหนด' : s.step_sla==='a' ? '⚠ ใกล้ครบ' : '✕ เกินกำหนด'}
                    </span>
                  )}
                  {isActive && (
                    <span style={{fontSize:11,fontWeight:600,
                      color: s.step_sla ? C[s.step_sla] : 'var(--info)',
                      background: `color-mix(in srgb,${s.step_sla ? C[s.step_sla] : 'var(--info)'} 12%,transparent)`,
                      padding:'1px 8px',borderRadius:10}}>
                      {s.days_remain !== null
                        ? s.days_remain >= 0 ? `⏱ เหลือ ${s.days_remain} วัน` : `🔴 เกิน ${Math.abs(s.days_remain)} วัน`
                        : '⏱ กำลังดำเนินการ'}
                    </span>
                  )}
                  {isPending && (
                    <span style={{fontSize:11,color:'var(--ink-3)',
                      padding:'1px 8px',borderRadius:10,
                      border:'1px solid var(--border)'}}>
                      รอดำเนินการ
                    </span>
                  )}
                </div>
              </div>

              {/* แถว 2: ผู้ดำเนินการ + วันที่ */}
              {(s.actor || s.started_at || s.completed_at) && (
                <div className="vcenter" style={{gap:10,marginTop:3,flexWrap:'wrap'}}>
                  {s.actor && <span style={{fontSize:12,color:'var(--ink-2)'}}>{s.actor}</span>}
                  {s.started_at && !isDone && (
                    <span className="vcenter" style={{gap:3,fontSize:11,color:'var(--ink-3)'}}>
                      <Icon name="calendar" style={{width:10,height:10}}/> เริ่ม {thShortDate(s.started_at)}
                    </span>
                  )}
                  {isDone && s.started_at && s.completed_at && (
                    <span style={{fontSize:11,color:'var(--ink-3)'}}>
                      {thShortDate(s.started_at)} → {thShortDate(s.completed_at)}
                      {s.days_used !== null && ` (${s.days_used} วัน)`}
                    </span>
                  )}
                </div>
              )}

              {/* progress bar (active เท่านั้น) */}
              {isActive && s.days_used !== null && (
                <div style={{marginTop:6}}>
                  <div style={{height:4,borderRadius:2,background:'var(--border)',overflow:'hidden'}}>
                    <div style={{
                      height:'100%',
                      width: pct + '%',
                      background: s.step_sla ? C[s.step_sla] : 'var(--info)',
                      borderRadius:2,
                      transition:'width .4s',
                    }}/>
                  </div>
                  <div style={{fontSize:10,color:'var(--ink-3)',marginTop:2}}>
                    ใช้ไป {s.days_used} จาก {s.days_allowed} วัน ({pct}%)
                  </div>
                </div>
              )}

              {/* ปุ่ม + inline date (canEdit) */}
              {canEdit && (
                <div className="vcenter" style={{gap:6,marginTop:6,flexWrap:'wrap'}}>
                  {!isDone && !isActive && (
                    <button className="btn btn-sm"
                      style={{fontSize:11,padding:'2px 10px',background:'var(--info)',color:'#fff',borderRadius:6}}
                      disabled={!!busy} onClick={()=>markStatus(s,'active')}>
                      {busy===s.step_key+':active' ? '…' : '▶ เริ่ม'}
                    </button>
                  )}
                  {isActive && (
                    <>
                      <button className="btn btn-sm btn-primary"
                        style={{fontSize:11,padding:'2px 10px',borderRadius:6}}
                        disabled={!!busy} onClick={()=>setDoneModal(s)}>
                        {busy===s.step_key+':done' ? '…' : '✓ เสร็จแล้ว'}
                      </button>
                      <span style={{fontSize:11,color:'var(--ink-3)'}}>เสร็จวันที่</span>
                      <InlineDatePicker s={s} field="completed_at"/>
                    </>
                  )}
                  {isDone && (
                    <button className="btn btn-sm btn-ghost"
                      style={{fontSize:11,padding:'2px 8px'}}
                      disabled={!!busy} onClick={()=>markStatus(s,'active')}>
                      ↩ ย้อนกลับ
                    </button>
                  )}
                  {/* บันทึกวันเริ่มย้อนหลัง */}
                  {(isActive || isDone) && (
                    <span className="vcenter" style={{gap:3,fontSize:11,color:'var(--ink-3)'}}>
                      เริ่ม <InlineDatePicker s={s} field="started_at"/>
                    </span>
                  )}
                </div>
              )}

              {/* note + attachment ที่บันทึกไว้ */}
              {(s.detail || s.attachment_name) && (
                <div style={{marginTop:6,display:'flex',flexDirection:'column',gap:4}}>
                  {s.detail && (
                    <div style={{fontSize:12,color:'var(--ink-2)',background:'var(--surface-2)',borderRadius:6,padding:'5px 10px',borderLeft:'3px solid var(--maroon)'}}>
                      {s.detail}
                    </div>
                  )}
                  {s.attachment_name && s.event_id && (
                    <button
                      onClick={() => {
                        const base = (window.__APP_BASE__ || '').replace(/\/$/, '');
                        setPdfModal({
                          url:      base + '/api/file.php?event=' + s.event_id + '&inline=1',
                          filename: s.attachment_name,
                        });
                      }}
                      style={{
                        display:'flex', alignItems:'center', gap:6,
                        background:'none', border:'none', cursor:'pointer',
                        padding:'4px 8px', borderRadius:6, fontSize:12,
                        color:'var(--info)', textAlign:'left',
                        transition:'background .15s',
                      }}
                      onMouseEnter={e=>e.currentTarget.style.background='var(--surface-2)'}
                      onMouseLeave={e=>e.currentTarget.style.background='none'}
                    >
                      <Icon name="file" style={{width:13,height:13,color:'var(--danger)',flexShrink:0}}/>
                      <span style={{textDecoration:'underline',textUnderlineOffset:2}}>{s.attachment_name}</span>
                      {s.attachment_size && <span style={{color:'var(--ink-3)',textDecoration:'none'}}>({s.attachment_size})</span>}
                    </button>
                  )}
                </div>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );

  return (
    <>
      {timeline}
      {doneModal && (
        <StepDoneModal
          step={doneModal}
          onConfirm={(payload) => confirmDone(doneModal, payload)}
          onClose={() => setDoneModal(null)}
        />
      )}
      {pdfModal && (
        <PdfModal
          url={pdfModal.url}
          filename={pdfModal.filename}
          onClose={() => setPdfModal(null)}
        />
      )}
    </>
  );
}

/* ---------------- รายละเอียดสำนวน ---------------- */
/* ---------- modal ยืนยันลบสำนวน ---------- */
function DeleteCaseModal({ c, onDeleted, onClose }) {
  const [input, setInput]   = useState('');
  const [busy, setBusy]     = useState(false);
  const [err, setErr]       = useState('');
  const confirmTarget = (c.reg && c.reg.trim() && c.reg !== '—') ? c.reg.trim() : c.id;
  const match = input.trim() === confirmTarget;

  const doDelete = async () => {
    if (!match) return;
    setBusy(true); setErr('');
    try {
      await apiFetch('/api/cases.php?id=' + c.id, {
        method: 'DELETE',
        body: JSON.stringify({ confirm_reg: input.trim() }),
      });
      onDeleted(c.id);
    } catch(e) { setErr(e.message); setBusy(false); }
  };

  return (
    <div style={{position:'fixed',inset:0,background:'rgba(20,10,12,.6)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:300,padding:24}} onClick={onClose}>
      <div style={{background:'var(--surface)',borderRadius:12,boxShadow:'0 8px 40px rgba(0,0,0,.4)',width:'100%',maxWidth:440,display:'flex',flexDirection:'column'}} onClick={e=>e.stopPropagation()}>
        <div style={{padding:'20px 24px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',gap:12}}>
          <Icon name="alert" style={{width:22,height:22,color:'var(--danger)',flexShrink:0}}/>
          <h3 style={{margin:0,fontSize:17,color:'var(--danger)'}}>ลบสำนวนออกจากระบบ</h3>
        </div>
        <div style={{padding:'20px 24px',display:'flex',flexDirection:'column',gap:14}}>
          <div style={{background:'rgba(200,30,30,.08)',border:'1px solid rgba(200,30,30,.25)',borderRadius:8,padding:'12px 14px',fontSize:14,lineHeight:1.6}}>
            <b>คำเตือน:</b> การลบสำนวนไม่สามารถย้อนกลับได้ ข้อมูล ไทม์ไลน์ และไฟล์แนบทั้งหมดจะถูกลบถาวร
          </div>
          <div style={{fontSize:14}}>
            <div style={{marginBottom:4}}>สำนวน: <b>{c.id}</b></div>
            <div style={{marginBottom:14}}>เรื่อง: <span className="muted">{c.subject}</span></div>
            <label style={{display:'block',marginBottom:6,fontWeight:500}}>
              พิมพ์ <b style={{color:'var(--danger)'}}>{confirmTarget}</b> เพื่อยืนยัน
            </label>
            <input className="input" autoFocus value={input} onChange={e=>setInput(e.target.value)}
              placeholder={confirmTarget} onKeyDown={e=>e.key==='Enter' && match && doDelete()}
              style={{borderColor: input && !match ? 'var(--danger)' : ''}}/>
            {input && !match && <div style={{fontSize:12,color:'var(--danger)',marginTop:4}}>เลขรับไม่ตรง</div>}
          </div>
          {err && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}
          <div className="btn-row" style={{marginTop:4}}>
            <button className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
            <button className="btn" style={{background:'var(--danger)',color:'#fff',opacity:match?1:0.45,cursor:match?'pointer':'not-allowed'}}
              disabled={!match||busy} onClick={doDelete}>
              {busy ? <LoadingSpinner/> : <><Icon name="x" style={{width:14,height:14}}/> ลบสำนวน</>}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function CaseDetail({ cid, cases, officers, back, updateCase, role, currentUser, onCaseDeleted }) {
  const [c, setC] = useState(() => cases.find(x=>x.id===cid) || null);
  const [tab, setTab] = useState("info");
  const [assign, setAssign] = useState(false);
  const [showDelete, setShowDelete] = useState(false);
  const [showPropose, setShowPropose] = useState(false);
  const [pdfModal, setPdfModal] = useState(null); // {url, filename}
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
  const canAssign = role==="officer" || role==="dir_legal" || role==="admin";
  const isHeadSec = role==="head_secretary";

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
            <span className="badge badge-maroon">{TRACKS[c.track]?.label||c.track} · {c.cat}</span>
            <span className={"badge "+(CLASS[c.cls]||CLASS.public).c}><Icon name="lock" style={{width:12,height:12}}/> ชั้น{(CLASS[c.cls]||CLASS.public).l}</span>
            <SLAText s={c.sla}/>
          </div>
        </div>
        <div className="vcenter" style={{gap:8}}>
          {canAssign && c.status!=="closed" &&
            <button className="btn btn-primary" onClick={()=>setAssign(true)}><Icon name="gavel" style={{width:16,height:16}}/> {o?"เปลี่ยนผู้สอบสวน":"แต่งตั้งผู้สอบสวน"}</button>}
          {isHeadSec && !c.assignee && c.status!=="closed" &&
            <button className="btn btn-primary" onClick={()=>setShowPropose(true)}>
              <Icon name="flag" style={{width:16,height:16}}/> นำเสนอมอบหมาย
            </button>}
          {role==="admin" &&
            <button className="btn btn-outline" style={{color:'var(--danger)',borderColor:'var(--danger)'}}
              onClick={()=>setShowDelete(true)}>
              <Icon name="x" style={{width:15,height:15}}/> ลบสำนวน
            </button>}
        </div>
      </div>

      <div className="grid" style={{gridTemplateColumns:"1.7fr 1fr",alignItems:"start"}}>
        <div className="card">
          <div className="tabs" style={{padding:"0 18px"}}>
            {[["info","รายละเอียด"],["tasks","งานย่อย"],["files","คลังสำนวน"],["timeline","ไทม์ไลน์ & SLA"]].map(([v,l])=>
              <button key={v} className={"tab "+(tab===v?"active":"")} onClick={()=>setTab(v)}>{l}</button>)}
          </div>
          <div className="card-pad" style={{padding:24}}>
            {tab==="tasks" && <CaseTasksTab caseId={c.id} caseAssignee={c.assignee} officers={officers} currentUser={currentUser} role={role}/>}
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
              {(c.files||[]).map((f,i)=>{
                const base = window.__APP_BASE__ || '';
                const isPdf = f.sn ? f.sn.toLowerCase().endsWith('.pdf') || f.n.toLowerCase().endsWith('.pdf') : f.n.toLowerCase().endsWith('.pdf');
                const fileUrl = f.sn ? base + '/api/file.php?case=' + encodeURIComponent(f.sn) : null;
                return (
                  <div key={i} className="file-row">
                    <Icon name="file" style={{width:18,height:18,color:isPdf?"#ef4444":"var(--maroon)"}}/>
                    <span style={{fontWeight:500,flex:1,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}}>{f.n}</span>
                    <span className={"badge "+(CLASS[f.c]||CLASS.public).c} style={{fontSize:11}}>{(CLASS[f.c]||CLASS.public).l}</span>
                    <span className="fmeta">{f.s}</span>
                    {isPdf && fileUrl && (
                      <button className="icon-btn" style={{width:30,height:30}} title="ดูไฟล์ PDF"
                        onClick={()=>setPdfModal({url: fileUrl+'&inline=1', filename: f.n})}>
                        <Icon name="eye" style={{width:15,height:15,color:'var(--accent)'}}/>
                      </button>
                    )}
                    {fileUrl && (
                      <a href={fileUrl} download={f.n} className="icon-btn" style={{width:30,height:30,display:'flex',alignItems:'center',justifyContent:'center'}} title="ดาวน์โหลด">
                        <Icon name="download" style={{width:15,height:15}}/>
                      </a>
                    )}
                  </div>
                );
              })}
              {(!c.files||c.files.length===0) && <div className="muted sm" style={{padding:'12px 0'}}>ยังไม่มีไฟล์แนบ</div>}
            </div>}
            {pdfModal && <PdfModal url={pdfModal.url} filename={pdfModal.filename} onClose={()=>setPdfModal(null)}/>}
            {tab==="timeline" && <CaseTimeline
              steps={(c.steps||[]).map(s=>({...s, _case_id: c.id}))}
              onRefresh={()=>{ api.getCase(c.id).then(full=>setC(full)); }}
              canEdit={canAssign}/>}
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

      {showPropose && <ProposeModal case_={c} officers={officers}
        onClose={()=>setShowPropose(false)}
        onSaved={()=>{ setShowPropose(false); back(); }}/>}
      {assign && <AssignModal c={c} officers={officers} close={()=>setAssign(false)} onAssign={async (oid)=>{
        await updateCase(c.id, {assignee:oid, status:["screening","received","case"].includes(c.status)?"assigned":c.status});
        api.getCase(c.id).then(full => setC(full));
        setAssign(false);
      }}/>}
      {showDelete && <DeleteCaseModal c={c} onClose={()=>setShowDelete(false)} onDeleted={(id)=>{
        setShowDelete(false);
        if (onCaseDeleted) onCaseDeleted(id);
        back();
      }}/>}
    </div>
  );
}

function AssignModal({ c, officers, close, onAssign }) {
  const pool = (officers||[]).filter(o=>o.group===(TRACKS[c.track]?.group||c.track));
  const [sel, setSel] = useState(c.assignee || (pool[0]&&pool[0].id) || "");
  return (
    <div className="overlay" onClick={close}>
      <div className="modal" onClick={e=>e.stopPropagation()}>
        <div className="modal-h">
          <div className="vcenter"><Icon name="gavel" style={{width:20,height:20,color:"var(--maroon)"}}/><h3 style={{fontSize:17}}>แต่งตั้งผู้สอบสวน / นิติกรเจ้าของเรื่อง</h3></div>
          <button className="icon-btn" onClick={close}><Icon name="x"/></button>
        </div>
        <div className="modal-b">
          <div className="notice notice-info" style={{marginBottom:16}}><Icon name="info"/><div>เรื่องนี้อยู่ในสาย <b>{TRACKS[c.track]?.label||c.track}</b> — แสดงเฉพาะนิติกรใน {TRACKS[c.track]?.group||c.track}</div></div>
          <div className="choices">
            {pool.map(o=>(
              <div key={o.id} className={"choice "+(sel===o.id?"active":"")} onClick={()=>setSel(o.id)}>
                <span className="radio"></span>
                <span className="avatar">{o.init}</span>
                <div style={{flex:1}}>
                  <div className="between"><div className="ct">{o.name}</div><span className="badge">{o.load} เรื่องในมือ</span></div>
                  <div className="cd">{o.duty || o.role}</div>
                  {o.duty && <div className="cd" style={{fontSize:11,opacity:.7}}>{o.role}</div>}
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
  const [d, setD] = useState({
    subject:"", group:"", channelType:"", channelItem:"",
    agency:"", priority:"ปกติ", cls:"public",
    complainant:"", address:"", contact:"", detail:"", files:[],
  });
  const set = (k,v) => setD(s=>({...s,[k]:v}));

  const [groups,       setGroups]       = useState([]);
  const [channelTypes, setChannelTypes] = useState([]);
  const [channelItems, setChannelItems] = useState([]);

  const fileRef = React.useRef();
  const [uploading, setUploading] = useState(false);
  const [uploadErr, setUploadErr] = useState("");

  const [done,    setDone]    = useState(false);
  const [saving,  setSaving]  = useState(false);
  const [saveErr, setSaveErr] = useState("");

  React.useEffect(() => {
    api.getLookups('group_name').then(setGroups).catch(()=>{});
    api.getChannelTypes().then(setChannelTypes).catch(()=>{});
  }, []);

  React.useEffect(() => {
    if (!d.channelType) { setChannelItems([]); set("channelItem",""); return; }
    api.getChannelItems(d.channelType).then(rows=>{ setChannelItems(rows); set("channelItem",""); }).catch(()=>{});
  }, [d.channelType]);

  const handleFiles = async (e) => {
    const files = Array.from(e.target.files);
    if (!files.length) return;
    setUploading(true); setUploadErr("");
    for (const file of files) {
      try {
        const res = await api.uploadTmpFile(file);
        setD(s=>({...s, files:[...s.files, {n:res.orig, s:res.size, tmp:res.tmp}]}));
      } catch(err) { setUploadErr(err.message); }
    }
    setUploading(false);
    e.target.value = '';
  };

  const channelFull = d.channelType && d.channelItem
    ? d.channelType + ' — ' + d.channelItem
    : d.channelType;
  const valid = d.subject.trim() && d.group && d.channelType;

  if (done) return (
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
    <div className="fade-in" style={{maxWidth:960}}>
      <button className="btn btn-ghost btn-sm" onClick={back} style={{marginBottom:12}}><Icon name="chevL" style={{width:16,height:16}}/> กลับ</button>
      <PageHead title="นำเข้าเรื่องจากเอกสาร" sub="ลงทะเบียนหนังสือราชการและเรื่องที่รับมาจากหน่วยงานภายนอกเข้าสู่ระบบ"/>
      <div className="notice notice-info" style={{marginBottom:18}}><Icon name="info"/><div>เมื่อบันทึก ระบบจะออกเลขรับเรื่อง แปลงเป็นสำนวน และเริ่มนับ SLA โดยอัตโนมัติ</div></div>
      <div className="grid" style={{gridTemplateColumns:"1.5fr 1fr",gap:18,alignItems:"start"}}>

        {/* ── คอลัมน์ซ้าย ── */}
        <div style={{display:"grid",gap:16}}>
          <div className="card card-pad" style={{display:"grid",gap:16}}>
            <div className="field"><label>หัวข้อเรื่อง <span className="req">*</span></label>
              <input className="input" value={d.subject} onChange={e=>set("subject",e.target.value)} placeholder="สรุปเรื่องจากเอกสาร"/></div>
            <div className="field"><label>กลุ่มงาน <span className="req">*</span></label>
              <select className="select" value={d.group} onChange={e=>set("group",e.target.value)}>
                <option value="">— เลือกกลุ่มงาน —</option>
                {groups.map(g=><option key={g.id} value={g.name}>{g.name}</option>)}
              </select></div>
            <div className="field"><label>หน่วยงาน/สถานศึกษาที่เกี่ยวข้อง</label>
              <input className="input" value={d.agency} onChange={e=>set("agency",e.target.value)} placeholder="เช่น วิทยาลัยเทคนิค..."/></div>
            <div className="field"><label>รายละเอียด</label>
              <textarea className="textarea" rows={4} value={d.detail} onChange={e=>set("detail",e.target.value)} placeholder="เนื้อหาโดยสรุปจากเอกสาร"/></div>
          </div>

          {/* ── แนบไฟล์ ── */}
          <div className="card card-pad" style={{display:"grid",gap:12}}>
            <h3 style={{fontSize:15}}>แนบสำเนาเอกสาร</h3>
            <input type="file" ref={fileRef} style={{display:"none"}} multiple accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" onChange={handleFiles}/>
            <div className="dropzone" onClick={()=>fileRef.current.click()} style={{cursor:"pointer"}}>
              <Icon name="paperclip" style={{width:22,height:22,color:"var(--maroon)",margin:"0 auto 6px"}}/>
              <div style={{fontWeight:600,fontSize:14}}>{uploading ? "กำลังอัปโหลด…" : "คลิกหรือลากไฟล์มาวาง"}</div>
              <div className="tiny muted" style={{marginTop:4}}>PDF, JPG, PNG, DOCX, XLSX — ไม่เกิน 20 MB ต่อไฟล์</div>
            </div>
            {uploadErr && <div className="notice notice-warn" style={{margin:0}}><Icon name="alert"/><div>{uploadErr}</div></div>}
            {d.files.map((f,i)=>(
              <div key={i} className="file-row" style={{marginTop:0}}>
                <Icon name="file" style={{width:17,height:17,color:"var(--maroon)"}}/>
                <span style={{fontWeight:500,flex:1}}>{f.n}</span>
                <span className="fmeta">{f.s}</span>
                <button className="btn btn-ghost btn-sm" style={{padding:"2px 6px"}} onClick={()=>setD(s=>({...s,files:s.files.filter((_,j)=>j!==i)}))}>✕</button>
              </div>
            ))}
          </div>
        </div>

        {/* ── คอลัมน์ขวา ── */}
        <div className="grid" style={{gap:16}}>
          <div className="card card-pad" style={{display:"grid",gap:14}}>
            <h3 style={{fontSize:15}}>ข้อมูลการลงทะเบียน</h3>

            {/* ประเภทหน่วยงาน */}
            <div className="field"><label>ประเภทหน่วยงาน <span className="req">*</span></label>
              <select className="select" value={d.channelType} onChange={e=>set("channelType",e.target.value)}>
                <option value="">— เลือกประเภท —</option>
                {channelTypes.map(t=><option key={t.id} value={t.name}>{t.name}</option>)}
              </select>
            </div>

            {/* หน่วยงานที่ส่งเรื่อง */}
            <div className="field"><label>หน่วยงานที่ส่งเรื่อง</label>
              <select className="select" value={d.channelItem} onChange={e=>set("channelItem",e.target.value)}
                disabled={!d.channelType || channelItems.length===0}>
                <option value="">{!d.channelType ? "— เลือกประเภทก่อน —" : "— เลือก —"}</option>
                {channelItems.map(i=><option key={i.id} value={i.name}>{i.name}</option>)}
              </select>
            </div>

            <div className="field"><label>ระดับความเร่งด่วน</label>
              <div className="seg" style={{flexWrap:"wrap"}}>
                {["ปกติ","ด่วน","ด่วนมาก","ด่วนที่สุด"].map(p=><button key={p} className={d.priority===p?"active":""} onClick={()=>set("priority",p)}>{p}</button>)}
              </div></div>
            <div className="field"><label>ชั้นความลับ</label>
              <select className="select" value={d.cls} onChange={e=>set("cls",e.target.value)}>
                {Object.entries(CLASS).map(([k,v])=><option key={k} value={k}>{v.l}</option>)}
              </select></div>
          </div>

          {/* ── ผู้ร้อง/ต้นเรื่อง ── */}
          <div className="card card-pad" style={{display:"grid",gap:14}}>
            <h3 style={{fontSize:15}}>ผู้ร้อง/ต้นเรื่อง (ถ้ามี)</h3>
            <div className="field"><label>ชื่อผู้ร้อง/ต้นเรื่อง</label>
              <input className="input" value={d.complainant} onChange={e=>set("complainant",e.target.value)} placeholder="ระบุ หรือเว้นว่างหากนิรนาม"/></div>
            <div className="field"><label>ที่อยู่</label>
              <textarea className="textarea" rows={2} value={d.address} onChange={e=>set("address",e.target.value)} placeholder="บ้านเลขที่ / ถนน / ตำบล / อำเภอ / จังหวัด"/></div>
            <div className="field"><label>เบอร์โทร / อีเมล</label>
              <input className="input" value={d.contact} onChange={e=>set("contact",e.target.value)} placeholder="0812345678 / email@example.com"/></div>
          </div>

          {saveErr && <div className="notice notice-warn"><Icon name="alert"/><div>{saveErr}</div></div>}
          <button className="btn btn-primary btn-lg btn-block" disabled={!valid||saving} onClick={async()=>{
            setSaving(true); setSaveErr("");
            try {
              await api.createCase({
                subject: d.subject, track: 'general', cat: d.group,
                channel: channelFull || 'หนังสือราชการ',
                agency: d.agency, priority: d.priority, cls: d.cls,
                complainant: d.complainant || null,
                contact: [d.address, d.contact].filter(Boolean).join(' | ') || null,
                detail: d.detail, identity: 'staff',
                tmp_files: d.files.map(f=>({tmp:f.tmp,orig:f.n,size:f.s})),
              });
              setDone(true);
            } catch(e) { setSaveErr(e.message); }
            finally { setSaving(false); }
          }}><Icon name="hash" style={{width:17,height:17}}/> ลงทะเบียน & แปลงเป็นสำนวน</button>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { PageHead, StatCard, OfficerDashboard, HeadSecretaryDashboard, CaseListPage, CaseDetail, AssignModal, ImportDocument, AssignProposalsPage });
