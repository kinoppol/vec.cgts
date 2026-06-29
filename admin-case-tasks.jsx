/* ============================================================
   admin-case-tasks.jsx — ระบบงานย่อย 5 ขั้นตอนของสำนวน
   ============================================================ */

const TASK_NAMES = ['รับเรื่อง','ตรวจสอบเอกสาร','ทำหนังสือ/จัดทำสำนวน','เสนอผู้บังคับบัญชา','ออกคำสั่ง/แจ้งผล'];

const TASK_STATUS_LABEL = { pending:'รอดำเนินการ', in_progress:'กำลังดำเนินการ', done:'เสร็จสิ้น' };
const TASK_STATUS_COLOR = { pending:'var(--ink-3)', in_progress:'var(--accent)', done:'var(--ok)' };

const MODAL_OVER = { position:'fixed',inset:0,background:'rgba(20,10,12,.6)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:300,padding:24 };
const MODAL_BOX  = { background:'var(--surface)',borderRadius:12,boxShadow:'0 8px 40px rgba(0,0,0,.4)',width:'100%',display:'flex',flexDirection:'column' };

/* -------- modal: เริ่มต้นงาน (admin/dir_legal เลือก officer ทำงาน 1) -------- */
function InitTasksModal({ caseId, officers, onDone, onClose }) {
  const [oid, setOid]   = useState(officers[0]?.id || '');
  const [due, setDue]   = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr]   = useState('');

  const submit = async () => {
    setBusy(true); setErr('');
    try {
      const res = await api.initCaseTasks({ case_id: caseId, officer_id: oid, due_date: due });
      onDone(res);
    } catch(e) { setErr(e.message); }
    setBusy(false);
  };

  return (
    <div style={MODAL_OVER} onClick={onClose}>
      <div style={{...MODAL_BOX, maxWidth:420}} onClick={e=>e.stopPropagation()}>
        <div style={{padding:'18px 22px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between',flexShrink:0}}>
          <h3 style={{margin:0,fontSize:16}}>เริ่มต้นรายการงาน</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <div style={{padding:'18px 22px',display:'flex',flexDirection:'column',gap:14}}>
          <p className="sm muted" style={{margin:0}}>ระบบจะสร้างรายการงาน 5 ขั้นตอนสำหรับสำนวนนี้ และมอบหมายงานที่ 1 ให้ผู้รับผิดชอบที่เลือก</p>
          {err && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}
          <div className="field">
            <label>ผู้รับผิดชอบงานที่ 1 (รับเรื่อง)</label>
            <select className="input" value={oid} onChange={e=>setOid(e.target.value)}>
              {officers.map(o=><option key={o.id} value={o.id}>{o.name}</option>)}
            </select>
          </div>
          <div className="field">
            <label>วันครบกำหนดงานที่ 1</label>
            <input className="input" type="date" value={due} onChange={e=>setDue(e.target.value)}/>
          </div>
          <div className="btn-row">
            <button className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
            <button className="btn btn-primary" disabled={busy||!oid} onClick={submit}>
              {busy ? <LoadingSpinner/> : 'เริ่มต้นรายการงาน'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

/* -------- modal: บันทึกความคืบหน้า -------- */
function ProgressModal({ task, onDone, onClose }) {
  const [progress, setProgress] = useState(task.progress || 0);
  const [note, setNote]         = useState(task.note || '');
  const [busy, setBusy]         = useState(false);

  const submit = async () => {
    setBusy(true);
    try {
      const res = await api.updateCaseTask(task.id, { progress, note });
      onDone(res);
    } catch(e) { alert(e.message); }
    setBusy(false);
  };

  return (
    <div style={MODAL_OVER} onClick={onClose}>
      <div style={{...MODAL_BOX, maxWidth:400}} onClick={e=>e.stopPropagation()}>
        <div style={{padding:'18px 22px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between',flexShrink:0}}>
          <h3 style={{margin:0,fontSize:16}}>งานที่ {task.task_no}: {task.task_name}</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <div style={{padding:'18px 22px',display:'flex',flexDirection:'column',gap:16}}>
          <div className="field">
            <label>ความคืบหน้า: <b>{progress}%</b></label>
            <input type="range" min={0} max={100} step={5} value={progress}
              onChange={e=>setProgress(+e.target.value)} style={{width:'100%',marginTop:6}}/>
            <div style={{display:'flex',justifyContent:'space-between',fontSize:11,color:'var(--ink-3)'}}>
              <span>0%</span><span>50%</span><span>100%</span>
            </div>
          </div>
          <div className="field">
            <label>หมายเหตุ</label>
            <textarea className="input" rows={3} value={note} onChange={e=>setNote(e.target.value)} placeholder="บันทึกความคืบหน้า..."/>
          </div>
          <div className="btn-row">
            <button className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
            <button className="btn btn-primary" disabled={busy} onClick={submit}>
              {busy ? <LoadingSpinner/> : 'บันทึก'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

/* -------- modal: เสนอส่งต่องาน -------- */
function CompleteTaskModal({ task, officers, onDone, onClose }) {
  const [propOfficer, setPropOfficer] = useState('');
  const [nextDue, setNextDue]         = useState('');
  const [note, setNote]               = useState('');
  const [busy, setBusy]               = useState(false);
  const [err, setErr]                 = useState('');
  const isLastTask = (task.task_no >= 5);

  const submit = async () => {
    if (!isLastTask && !propOfficer) { setErr('กรุณาเลือกผู้รับผิดชอบงานถัดไป'); return; }
    setBusy(true); setErr('');
    try {
      const res = await api.completeTask(task.id, { proposed_officer: propOfficer, next_due_date: nextDue, note });
      onDone(res);
    } catch(e) { setErr(e.message); }
    setBusy(false);
  };

  return (
    <div style={MODAL_OVER} onClick={onClose}>
      <div style={{...MODAL_BOX, maxWidth:460}} onClick={e=>e.stopPropagation()}>
        <div style={{padding:'18px 22px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between',flexShrink:0}}>
          <h3 style={{margin:0,fontSize:16}}>เสร็จสิ้นงานที่ {task.task_no}</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <div style={{padding:'18px 22px',display:'flex',flexDirection:'column',gap:14}}>
          <div style={{background:'rgba(0,140,60,.08)',border:'1px solid rgba(0,140,60,.25)',borderRadius:8,padding:'10px 14px',fontSize:13}}>
            ยืนยันว่างาน <b>{task.task_name}</b> เสร็จสิ้นแล้ว
            {!isLastTask && ' และเสนอผู้รับผิดชอบงานถัดไปเพื่อรอการอนุมัติ'}
          </div>
          {err && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}
          {!isLastTask && (<>
            <div className="field">
              <label>เสนอผู้รับผิดชอบงานที่ {task.task_no+1} ({TASK_NAMES[task.task_no]}) <span className="req">*</span></label>
              <select className="input" value={propOfficer} onChange={e=>setPropOfficer(e.target.value)}>
                <option value="">— เลือกบุคลากร —</option>
                {officers.map(o=><option key={o.id} value={o.id}>{o.name}</option>)}
              </select>
            </div>
            <div className="field">
              <label>วันครบกำหนดงานถัดไป</label>
              <input className="input" type="date" value={nextDue} onChange={e=>setNextDue(e.target.value)}/>
            </div>
          </>)}
          <div className="field">
            <label>หมายเหตุ / รายงานผล</label>
            <textarea className="input" rows={3} value={note} onChange={e=>setNote(e.target.value)} placeholder="สรุปผลการดำเนินงาน..."/>
          </div>
          <div className="btn-row">
            <button className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
            <button className="btn btn-primary" disabled={busy||(!isLastTask&&!propOfficer)} onClick={submit}>
              {busy ? <LoadingSpinner/> : isLastTask ? 'ยืนยันเสร็จสิ้น' : 'เสนอมอบหมายงานถัดไป →'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

/* -------- modal: อนุมัติ/เปลี่ยนแปลงการเสนอ -------- */
function ReviewProposalModal({ proposal, officers, onDone, onClose }) {
  const [finalOfficer, setFinalOfficer] = useState(proposal.proposed_officer || '');
  const [note, setNote]                 = useState('');
  const [busy, setBusy]                 = useState(false);
  const [err, setErr]                   = useState('');
  const changed = finalOfficer !== proposal.proposed_officer;

  const submit = async () => {
    setBusy(true); setErr('');
    try {
      const res = await api.approveProposal(proposal.id, { final_officer: finalOfficer, review_note: note });
      onDone(res);
    } catch(e) { setErr(e.message); }
    setBusy(false);
  };

  return (
    <div style={MODAL_OVER} onClick={onClose}>
      <div style={{...MODAL_BOX, maxWidth:480}} onClick={e=>e.stopPropagation()}>
        <div style={{padding:'18px 22px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between',flexShrink:0}}>
          <h3 style={{margin:0,fontSize:16}}>อนุมัติการมอบหมายงาน</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <div style={{padding:'18px 22px',display:'flex',flexDirection:'column',gap:14}}>
          <div style={{fontSize:13,display:'grid',gap:4,background:'var(--bg-2,rgba(0,0,0,.04))',padding:'12px 14px',borderRadius:8}}>
            <div>งานที่ <b>{proposal.from_task_no}</b> เสร็จสิ้นแล้ว</div>
            <div>เสนอมอบหมายงานที่ <b>{proposal.to_task_no} ({TASK_NAMES[proposal.to_task_no-1]})</b> ให้:</div>
            <div style={{fontWeight:600,fontSize:14,color:'var(--accent)'}}>{proposal.proposed_name}</div>
            {proposal.propose_note && <div className="muted" style={{fontSize:12,marginTop:4}}>หมายเหตุ: {proposal.propose_note}</div>}
          </div>
          {err && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}
          <div className="field">
            <label>ผู้รับผิดชอบ {changed ? <span style={{color:'var(--accent)',fontSize:12}}>(เปลี่ยนแปลง)</span> : ''}</label>
            <select className="input" value={finalOfficer} onChange={e=>setFinalOfficer(e.target.value)}>
              {officers.map(o=><option key={o.id} value={o.id}>{o.name}</option>)}
            </select>
            {!changed && <span className="hint">ตรงกับที่เสนอ — กดอนุมัติได้เลย</span>}
          </div>
          {changed && (
            <div className="field">
              <label>เหตุผลที่เปลี่ยนแปลง <span className="req">*</span></label>
              <textarea className="input" rows={2} value={note} onChange={e=>setNote(e.target.value)} placeholder="ระบุเหตุผลที่เปลี่ยนผู้รับผิดชอบ..."/>
            </div>
          )}
          <div className="btn-row">
            <button className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
            <button className="btn btn-primary" disabled={busy||(changed&&!note)} onClick={submit}>
              {busy ? <LoadingSpinner/> : changed ? 'เปลี่ยนแปลงและอนุมัติ' : 'อนุมัติตามที่เสนอ'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

/* -------- card งานย่อย 1 ใบ -------- */
function TaskCard({ task, proposal, isMyTask, canReview, officers, onRefresh }) {
  const [modal, setModal] = useState(null); // 'progress'|'complete'|'review'

  const statusColor = TASK_STATUS_COLOR[task.status];
  const isDone      = task.status === 'done';
  const isActive    = task.status === 'in_progress';

  return (
    <div style={{
      border: `1.5px solid ${isActive ? 'var(--accent)' : isDone ? 'var(--ok)' : 'var(--line)'}`,
      borderRadius: 10, padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 10,
      opacity: task.status === 'pending' ? 0.65 : 1,
      background: isActive ? 'rgba(120,20,30,.03)' : 'var(--surface)',
    }}>
      {/* header */}
      <div style={{display:'flex',alignItems:'flex-start',gap:10}}>
        <div style={{
          width:28,height:28,borderRadius:'50%',background:statusColor,display:'flex',alignItems:'center',justifyContent:'center',
          color:'#fff',fontSize:13,fontWeight:700,flexShrink:0
        }}>{task.task_no}</div>
        <div style={{flex:1}}>
          <div style={{fontWeight:600,fontSize:14}}>{task.task_name}</div>
          <div style={{fontSize:12,color:statusColor,marginTop:2}}>{TASK_STATUS_LABEL[task.status]}</div>
        </div>
        {isDone && <Icon name="checkCircle" style={{width:18,height:18,color:'var(--ok)',flexShrink:0}}/>}
      </div>

      {/* ผู้รับผิดชอบ + วัน */}
      <div style={{display:'flex',gap:12,flexWrap:'wrap',fontSize:13}}>
        {task.officer_name ? (
          <div className="vcenter" style={{gap:5}}>
            <span className="avatar avatar-sm" style={{width:22,height:22,fontSize:10}}>{task.officer_init||'?'}</span>
            <span>{task.officer_name}</span>
          </div>
        ) : <span className="faint">ยังไม่มีผู้รับผิดชอบ</span>}
        {task.due_date && <span className="faint">· ครบกำหนด {thDate(task.due_date)}</span>}
      </div>

      {/* progress bar */}
      {(isActive || isDone) && (
        <div>
          <div style={{display:'flex',justifyContent:'space-between',fontSize:11,marginBottom:4}}>
            <span className="faint">ความคืบหน้า</span>
            <span style={{fontWeight:600}}>{task.progress}%</span>
          </div>
          <div style={{height:6,background:'var(--line)',borderRadius:3,overflow:'hidden'}}>
            <div style={{height:'100%',width:task.progress+'%',background:isDone?'var(--ok)':'var(--accent)',borderRadius:3,transition:'width .3s'}}/>
          </div>
        </div>
      )}

      {/* note */}
      {task.note && <div style={{fontSize:12,color:'var(--ink-2)',background:'var(--bg-2,rgba(0,0,0,.04))',padding:'6px 10px',borderRadius:6}}>{task.note}</div>}

      {/* pending proposal badge */}
      {proposal && proposal.status === 'pending' && (
        <div style={{background:'rgba(240,160,0,.12)',border:'1px solid rgba(240,160,0,.3)',borderRadius:6,padding:'6px 10px',fontSize:12,display:'flex',alignItems:'center',gap:6}}>
          <Icon name="clock" style={{width:13,height:13,color:'var(--warn,#c88000)'}}/>
          <span>รอการอนุมัติ — เสนอ <b>{proposal.proposed_name}</b> รับงานถัดไป</span>
        </div>
      )}
      {proposal && proposal.status !== 'pending' && (
        <div style={{background:'rgba(0,140,60,.08)',border:'1px solid rgba(0,140,60,.25)',borderRadius:6,padding:'6px 10px',fontSize:12,display:'flex',gap:6}}>
          <Icon name="checkCircle" style={{width:13,height:13,color:'var(--ok)',flexShrink:0}}/>
          <span>{proposal.status==='changed'?'เปลี่ยนแปลง':'อนุมัติ'}โดย {proposal.reviewed_by_name} → {proposal.final_name||proposal.proposed_name}</span>
        </div>
      )}

      {/* action buttons */}
      {isActive && isMyTask && !proposal && (
        <div style={{display:'flex',gap:8,marginTop:2}}>
          <button className="btn btn-outline btn-sm" onClick={()=>setModal('progress')}>
            <Icon name="edit" style={{width:13,height:13}}/> บันทึกความคืบหน้า
          </button>
          <button className="btn btn-primary btn-sm" onClick={()=>setModal('complete')}>
            <Icon name="checkCircle" style={{width:13,height:13}}/> เสร็จแล้ว / เสนอส่งต่อ
          </button>
        </div>
      )}

      {canReview && proposal && proposal.status === 'pending' && (
        <button className="btn btn-primary btn-sm" style={{alignSelf:'flex-start'}} onClick={()=>setModal('review')}>
          <Icon name="gavel" style={{width:13,height:13}}/> อนุมัติ / เปลี่ยนแปลง
        </button>
      )}

      {/* modals */}
      {modal === 'progress' && <ProgressModal task={task} onDone={d=>{onRefresh(d);setModal(null)}} onClose={()=>setModal(null)}/>}
      {modal === 'complete' && <CompleteTaskModal task={task} officers={officers} onDone={d=>{onRefresh(d);setModal(null)}} onClose={()=>setModal(null)}/>}
      {modal === 'review'   && <ReviewProposalModal proposal={proposal} officers={officers} onDone={d=>{onRefresh(d);setModal(null)}} onClose={()=>setModal(null)}/>}
    </div>
  );
}

/* -------- main tab component -------- */
function CaseTasksTab({ caseId, caseAssignee, officers, currentUser, role }) {
  const [data, setData]   = useState(null); // { tasks, proposals }
  const [loading, setLoading] = useState(true);
  const [showInit, setShowInit] = useState(false);

  const load = () => {
    setLoading(true);
    api.getCaseTasks(caseId)
      .then(setData).catch(console.error)
      .finally(()=>setLoading(false));
  };
  useEffect(load, [caseId]);

  if (loading) return <div style={{padding:40,textAlign:'center'}}><LoadingSpinner/></div>;

  const tasks = data?.tasks || [];
  const proposals = data?.proposals || [];
  const canManage = ['admin','dir_legal','dir_admin'].includes(role);

  // map proposal ล่าสุดต่อ from_task_no
  const propByFrom = {};
  for (const p of proposals) {
    if (!propByFrom[p.from_task_no] || p.id > propByFrom[p.from_task_no].id) propByFrom[p.from_task_no] = p;
  }

  // my officer_id
  const myOfficerId = currentUser?.officer_id || null;

  if (tasks.length === 0) {
    return (
      <div style={{padding:32,textAlign:'center',display:'flex',flexDirection:'column',alignItems:'center',gap:14}}>
        <div style={{width:56,height:56,borderRadius:'50%',background:'var(--line)',display:'flex',alignItems:'center',justifyContent:'center'}}>
          <Icon name="clipboard" style={{width:24,height:24,color:'var(--ink-3)'}}/>
        </div>
        <div>
          <div style={{fontWeight:600,marginBottom:4}}>ยังไม่มีรายการงาน</div>
          <div className="muted sm">รายการงาน 5 ขั้นตอนจะถูกสร้างเมื่อเริ่มดำเนินการสำนวน</div>
        </div>
        {canManage && (
          <button className="btn btn-primary" onClick={()=>setShowInit(true)}>
            <Icon name="plus" style={{width:15,height:15}}/> เริ่มต้นรายการงาน
          </button>
        )}
        {showInit && <InitTasksModal caseId={caseId} officers={officers} onDone={d=>{setData(d);setShowInit(false)}} onClose={()=>setShowInit(false)}/>}
      </div>
    );
  }

  // pending proposals รอ approve
  const pendingProps = proposals.filter(p=>p.status==='pending');

  return (
    <div style={{padding:'18px 0',display:'flex',flexDirection:'column',gap:16}}>
      {/* summary bar */}
      <div style={{display:'flex',gap:8,alignItems:'center',flexWrap:'wrap',padding:'0 4px'}}>
        {tasks.map(t=>(
          <div key={t.id} style={{display:'flex',alignItems:'center',gap:5,fontSize:12,color:TASK_STATUS_COLOR[t.status]}}>
            <div style={{width:8,height:8,borderRadius:'50%',background:TASK_STATUS_COLOR[t.status]}}/>
            {t.task_no}. {t.task_name.split('/')[0]}
            {t.task_no < tasks.length && <span style={{color:'var(--line)',marginLeft:2}}>›</span>}
          </div>
        ))}
      </div>

      {/* pending proposal alert */}
      {canManage && pendingProps.length > 0 && (
        <div style={{background:'rgba(200,130,0,.1)',border:'1px solid rgba(200,130,0,.35)',borderRadius:8,padding:'10px 14px',fontSize:13,display:'flex',alignItems:'center',gap:8}}>
          <Icon name="alert" style={{width:16,height:16,color:'var(--warn,#c88000)',flexShrink:0}}/>
          <span>มี <b>{pendingProps.length}</b> รายการรอการอนุมัติมอบหมายงาน</span>
        </div>
      )}

      {/* task cards */}
      <div style={{display:'flex',flexDirection:'column',gap:10}}>
        {tasks.map(t => (
          <TaskCard
            key={t.id}
            task={t}
            proposal={propByFrom[t.task_no] || null}
            isMyTask={myOfficerId && t.officer_id === myOfficerId}
            canReview={canManage}
            officers={officers}
            onRefresh={d=>setData(d)}
          />
        ))}
      </div>
    </div>
  );
}

Object.assign(window, { CaseTasksTab });
