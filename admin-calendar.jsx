/* ============================================================
   admin-calendar.jsx — ปฏิทินกิจกรรมและงานครบกำหนด
   ============================================================ */

const TH_DAYS_FULL  = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
const TH_DAYS_SHORT = ['อา','จ','อ','พ','พฤ','ศ','ส'];
const TH_MONTHS     = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                        'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];

const CAL_TYPES = {
  due_date:      { label:'ครบกำหนด',     color:'#dc2626', bg:'#fef2f2', icon:'clock'   },
  meeting:       { label:'ประชุม',        color:'#2563eb', bg:'#eff6ff', icon:'users'   },
  court:         { label:'นัดศาล',        color:'#7c3aed', bg:'#f5f3ff', icon:'gavel'   },
  investigation: { label:'นัดสอบสวน',    color:'#d97706', bg:'#fffbeb', icon:'search'  },
  document:      { label:'นัดส่งเอกสาร', color:'#059669', bg:'#ecfdf5', icon:'file'    },
  committee:     { label:'นัดคณะกรรมการ',color:'#0891b2', bg:'#ecfeff', icon:'star'    },
};
const TYPE_OPTS = Object.entries(CAL_TYPES).filter(([k])=>k!=='due_date');

const MODAL_STYLE = {
  overlay: { position:'fixed',inset:0,background:'rgba(0,0,0,.5)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:300,padding:16 },
  box:     { background:'var(--surface)',borderRadius:12,boxShadow:'0 8px 40px rgba(0,0,0,.3)',width:'100%',maxWidth:460,display:'flex',flexDirection:'column',maxHeight:'90vh' },
  head:    { padding:'16px 20px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between',flexShrink:0 },
  body:    { padding:'16px 20px',overflowY:'auto',display:'flex',flexDirection:'column',gap:12 },
};

/* ---- รูปแบบวันที่ YYYY-MM-DD ---- */
const isoDate = d => d ? d.toISOString().slice(0,10) : '';
const today   = isoDate(new Date());

/* ---- chip กิจกรรม ---- */
function EventChip({ ev, onClick, compact }) {
  const t = CAL_TYPES[ev._type] || CAL_TYPES.meeting;
  return (
    <div onClick={e=>{e.stopPropagation();onClick&&onClick(ev)}} style={{
      background: t.bg, color: t.color,
      borderLeft: `3px solid ${t.color}`,
      borderRadius: 4, padding: compact ? '1px 5px' : '2px 7px',
      fontSize: compact ? 10 : 11, fontWeight: 500,
      cursor: 'pointer', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
      lineHeight: 1.5,
    }}>
      {compact ? ev.title : `${ev.start_time ? ev.start_time.slice(0,5)+' ' : ''}${ev.title}`}
    </div>
  );
}

/* ---- modal เพิ่ม / แก้ไข ---- */
function EventModal({ initial, officers, onSave, onDelete, onClose }) {
  const isNew = !initial?.id;
  const [form, setForm] = useState(initial ? { ...initial } : {
    event_type:'meeting', title:'', event_date: initial?.event_date || today,
    start_time:'', end_time:'', officer_id:'', case_id:'', note:'',
  });
  const [busy, setBusy] = useState(false);
  const [err, setErr]   = useState('');
  const set = (k,v) => setForm(f=>({...f,[k]:v}));

  const submit = async e => {
    e.preventDefault(); setBusy(true); setErr('');
    try {
      const saved = isNew
        ? await api.createCalEvent(form)
        : await api.updateCalEvent(initial.id, form);
      onSave(saved, isNew);
    } catch(e) { setErr(e.message); }
    setBusy(false);
  };

  const doDelete = async () => {
    if (!confirm(`ลบ "${form.title}"?`)) return;
    try { await api.deleteCalEvent(initial.id); onDelete(initial.id); }
    catch(e) { alert(e.message); }
  };

  return (
    <div style={MODAL_STYLE.overlay} onClick={onClose}>
      <div style={MODAL_STYLE.box} onClick={e=>e.stopPropagation()}>
        <div style={MODAL_STYLE.head}>
          <h3 style={{margin:0,fontSize:16}}>{isNew?'เพิ่มกิจกรรม':'แก้ไขกิจกรรม'}</h3>
          <button className="icon-btn" onClick={onClose}><Icon name="x"/></button>
        </div>
        <form onSubmit={submit} style={MODAL_STYLE.body}>
          {err && <div className="notice notice-err"><Icon name="alert"/><div>{err}</div></div>}

          <div className="field">
            <label>ประเภทกิจกรรม <span className="req">*</span></label>
            <div style={{display:'flex',gap:6,flexWrap:'wrap'}}>
              {TYPE_OPTS.map(([k,t])=>(
                <button key={k} type="button"
                  onClick={()=>set('event_type',k)}
                  style={{padding:'4px 10px',borderRadius:6,fontSize:12,fontWeight:500,cursor:'pointer',border:`1.5px solid ${form.event_type===k?t.color:'var(--line)'}`,background:form.event_type===k?t.bg:'transparent',color:form.event_type===k?t.color:'var(--ink-2)'}}>
                  {t.label}
                </button>
              ))}
            </div>
          </div>

          <div className="field">
            <label>ชื่อกิจกรรม <span className="req">*</span></label>
            <input className="input" required value={form.title} onChange={e=>set('title',e.target.value)} placeholder="ระบุชื่อกิจกรรม..."/>
          </div>

          <div style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:10}}>
            <div className="field" style={{gridColumn:'1/-1'}}>
              <label>วันที่ <span className="req">*</span></label>
              <input className="input" type="date" required value={form.event_date} onChange={e=>set('event_date',e.target.value)}/>
            </div>
            <div className="field">
              <label>เวลาเริ่ม</label>
              <input className="input" type="time" value={form.start_time||''} onChange={e=>set('start_time',e.target.value)}/>
            </div>
            <div className="field">
              <label>เวลาสิ้นสุด</label>
              <input className="input" type="time" value={form.end_time||''} onChange={e=>set('end_time',e.target.value)}/>
            </div>
          </div>

          <div className="field">
            <label>ผู้รับผิดชอบ</label>
            <select className="input" value={form.officer_id||''} onChange={e=>set('officer_id',e.target.value)}>
              <option value="">— ไม่ระบุ —</option>
              {(officers||[]).map(o=><option key={o.id} value={o.id}>{o.name}</option>)}
            </select>
          </div>

          <div className="field">
            <label>หมายเหตุ</label>
            <textarea className="input" rows={2} value={form.note||''} onChange={e=>set('note',e.target.value)} placeholder="รายละเอียดเพิ่มเติม..."/>
          </div>

          <div className="btn-row" style={{marginTop:4}}>
            {!isNew && <button type="button" className="btn btn-ghost" style={{color:'var(--danger)',marginRight:'auto'}} onClick={doDelete}>ลบ</button>}
            <button type="button" className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
            <button type="submit" className="btn btn-primary" disabled={busy}>
              {busy?<LoadingSpinner/>:isNew?'เพิ่มกิจกรรม':'บันทึก'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

/* ======================================================
   MONTH VIEW
   ====================================================== */
function MonthView({ year, month, allEvents, onDayClick, onEventClick }) {
  const firstDay = new Date(year, month-1, 1).getDay(); // 0=Sun
  const daysInMonth = new Date(year, month, 0).getDate();
  const cells = [];

  // empty cells before first day
  for (let i=0; i<firstDay; i++) cells.push(null);
  for (let d=1; d<=daysInMonth; d++) cells.push(d);
  // pad to complete last row
  while (cells.length % 7 !== 0) cells.push(null);

  const evByDate = {};
  for (const ev of allEvents) {
    if (!evByDate[ev.event_date]) evByDate[ev.event_date] = [];
    evByDate[ev.event_date].push(ev);
  }

  return (
    <div style={{display:'grid',gridTemplateColumns:'repeat(7,1fr)',gap:1,background:'var(--line)'}}>
      {TH_DAYS_SHORT.map(d=>(
        <div key={d} style={{background:'var(--bg-2,rgba(0,0,0,.04))',padding:'6px',textAlign:'center',fontSize:12,fontWeight:600,color:'var(--ink-2)'}}>
          {d}
        </div>
      ))}
      {cells.map((d,i)=>{
        if (!d) return <div key={i} style={{background:'var(--surface)',minHeight:90}}/>;
        const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const dayEvs = evByDate[dateStr] || [];
        const isToday = dateStr === today;
        const isPast  = dateStr < today;
        return (
          <div key={i} onClick={()=>onDayClick(dateStr)} style={{
            background:'var(--surface)', minHeight:90, padding:'4px 5px',
            cursor:'pointer', position:'relative',
            borderTop: isToday ? '2px solid var(--accent)' : 'none',
            opacity: isPast && dayEvs.length===0 ? 0.5 : 1,
          }}
          onMouseEnter={e=>e.currentTarget.style.background='var(--bg-2,rgba(0,0,0,.03))'}
          onMouseLeave={e=>e.currentTarget.style.background='var(--surface)'}>
            <div style={{
              width:24, height:24, borderRadius:'50%', display:'flex', alignItems:'center', justifyContent:'center',
              marginBottom:3, fontSize:13, fontWeight: isToday?700:400,
              background: isToday?'var(--accent)':'transparent',
              color: isToday?'#fff':'var(--ink-1)',
            }}>{d}</div>
            <div style={{display:'flex',flexDirection:'column',gap:2}}>
              {dayEvs.slice(0,3).map((ev,j)=>(
                <EventChip key={j} ev={ev} compact onClick={onEventClick}/>
              ))}
              {dayEvs.length>3 && <div style={{fontSize:10,color:'var(--ink-3)',paddingLeft:4}}>+{dayEvs.length-3} อื่นๆ</div>}
            </div>
          </div>
        );
      })}
    </div>
  );
}

/* ======================================================
   WEEK VIEW
   ====================================================== */
function WeekView({ year, month, weekStart, allEvents, onDayClick, onEventClick }) {
  const days = [];
  for (let i=0; i<7; i++) {
    const d = new Date(weekStart);
    d.setDate(d.getDate()+i);
    days.push(d);
  }

  const evByDate = {};
  for (const ev of allEvents) {
    if (!evByDate[ev.event_date]) evByDate[ev.event_date] = [];
    evByDate[ev.event_date].push(ev);
  }

  return (
    <div style={{display:'grid',gridTemplateColumns:'repeat(7,1fr)',gap:1,background:'var(--line)'}}>
      {days.map((d,i)=>{
        const dateStr = isoDate(d);
        const dayEvs  = evByDate[dateStr] || [];
        const isToday = dateStr === today;
        const thDay   = d.getDate() + ' ' + TH_MONTHS[d.getMonth()].slice(0,3) + '.';
        return (
          <div key={i} style={{background:'var(--surface)'}}>
            <div onClick={()=>onDayClick(dateStr)} style={{
              padding:'8px 8px 6px', textAlign:'center', cursor:'pointer',
              background: isToday?'var(--accent)':'var(--bg-2,rgba(0,0,0,.04))',
              color: isToday?'#fff':'var(--ink-1)',
            }}>
              <div style={{fontSize:11,opacity:.8}}>{TH_DAYS_SHORT[d.getDay()]}</div>
              <div style={{fontSize:14,fontWeight:600}}>{thDay}</div>
            </div>
            <div style={{minHeight:180,padding:'6px 5px',display:'flex',flexDirection:'column',gap:4}}>
              {dayEvs.map((ev,j)=><EventChip key={j} ev={ev} onClick={onEventClick}/>)}
              {dayEvs.length===0 && (
                <div onClick={()=>onDayClick(dateStr)} style={{flex:1,cursor:'pointer'}}/>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}

/* ======================================================
   DAY VIEW
   ====================================================== */
function DayView({ dateStr, allEvents, onAddClick, onEventClick }) {
  const dayEvs = allEvents.filter(e=>e.event_date===dateStr);
  const hours   = Array.from({length:14}, (_,i)=>i+7); // 07:00–20:00
  const d       = new Date(dateStr+'T00:00:00');
  const isToday = dateStr === today;

  const evByHour = {};
  for (const ev of dayEvs) {
    if (ev.start_time) {
      const h = parseInt(ev.start_time.slice(0,2));
      if (!evByHour[h]) evByHour[h] = [];
      evByHour[h].push(ev);
    }
  }
  const untimedEvs = dayEvs.filter(e=>!e.start_time);

  return (
    <div>
      <div style={{padding:'10px 16px',background:isToday?'var(--accent)':'var(--bg-2,rgba(0,0,0,.04))',marginBottom:1,display:'flex',alignItems:'center',gap:10}}>
        <span style={{fontSize:15,fontWeight:600,color:isToday?'#fff':'var(--ink-1)'}}>
          {TH_DAYS_FULL[d.getDay()]} {d.getDate()} {TH_MONTHS[d.getMonth()]} {d.getFullYear()+543}
        </span>
        <button className="btn btn-primary btn-sm" onClick={onAddClick} style={{marginLeft:'auto',background:isToday?'rgba(255,255,255,.2)':''}}>
          <Icon name="plus" style={{width:13,height:13}}/> เพิ่มกิจกรรม
        </button>
      </div>
      {untimedEvs.length>0 && (
        <div style={{padding:'8px 16px',display:'flex',gap:6,flexWrap:'wrap',borderBottom:'1px solid var(--line)'}}>
          <span style={{fontSize:11,color:'var(--ink-3)'}}>ตลอดวัน:</span>
          {untimedEvs.map((ev,i)=><EventChip key={i} ev={ev} onClick={onEventClick}/>)}
        </div>
      )}
      <div style={{display:'grid',gridTemplateColumns:'52px 1fr',background:'var(--surface)'}}>
        {hours.map(h=>(
          <React.Fragment key={h}>
            <div style={{padding:'8px 8px 8px 0',textAlign:'right',fontSize:11,color:'var(--ink-3)',borderTop:'1px solid var(--line)'}}>
              {String(h).padStart(2,'0')}:00
            </div>
            <div onClick={()=>onAddClick()} style={{borderTop:'1px solid var(--line)',minHeight:52,padding:4,cursor:'pointer',display:'flex',flexDirection:'column',gap:3}}
              onMouseEnter={e=>e.currentTarget.style.background='var(--bg-2,rgba(0,0,0,.03))'}
              onMouseLeave={e=>e.currentTarget.style.background=''}>
              {(evByHour[h]||[]).map((ev,j)=><EventChip key={j} ev={ev} onClick={onEventClick}/>)}
            </div>
          </React.Fragment>
        ))}
      </div>
    </div>
  );
}

/* ======================================================
   MAIN CalendarPage
   ====================================================== */
function CalendarPage({ officers, currentUser }) {
  const now = new Date();
  const [viewMode, setViewMode]   = useState('month');   // month | week | day
  const [year,  setYear]          = useState(now.getFullYear());
  const [month, setMonth]         = useState(now.getMonth()+1);
  const [selDay, setSelDay]       = useState(today);
  const [weekStart, setWeekStart] = useState(() => {
    const d = new Date(); d.setDate(d.getDate()-d.getDay()); return d;
  });
  const [data, setData]     = useState({events:[],due_dates:[]});
  const [loading, setLoading] = useState(true);
  const [modal, setModal]   = useState(null); // {type:'add'|'edit', ev?, date?}

  const load = (y=year, m=month) => {
    setLoading(true);
    api.getCalendar(y, m).then(d=>{
      // รวม due_dates เข้า events array
      const dueDateEvs = (d.due_dates||[]).map(dd=>({
        ...dd, _type:'due_date', title: dd.subject,
        event_date: dd.due_date, id:'dd_'+dd.case_id,
      }));
      const calEvs = (d.events||[]).map(e=>({...e, _type: e.event_type}));
      setData({ ...d, _merged: [...calEvs, ...dueDateEvs] });
    }).catch(console.error).finally(()=>setLoading(false));
  };

  useEffect(()=>{ load(year,month); }, [year,month]);

  // navigation
  const prevMonth = () => { if(month===1){setYear(y=>y-1);setMonth(12);}else setMonth(m=>m-1); };
  const nextMonth = () => { if(month===12){setYear(y=>y+1);setMonth(1);}else setMonth(m=>m+1); };
  const prevWeek  = () => { const d=new Date(weekStart); d.setDate(d.getDate()-7); setWeekStart(d); };
  const nextWeek  = () => { const d=new Date(weekStart); d.setDate(d.getDate()+7); setWeekStart(d); };
  const prevDay   = () => { const d=new Date(selDay+'T00:00:00'); d.setDate(d.getDate()-1); setSelDay(isoDate(d)); };
  const nextDay   = () => { const d=new Date(selDay+'T00:00:00'); d.setDate(d.getDate()+1); setSelDay(isoDate(d)); };

  const allEvents = data._merged || [];

  // header label
  const headerLabel = viewMode==='month'
    ? `${TH_MONTHS[month-1]} ${year+543}`
    : viewMode==='week'
    ? (() => { const e=new Date(weekStart); e.setDate(e.getDate()+6);
        return `${weekStart.getDate()} ${TH_MONTHS[weekStart.getMonth()].slice(0,3)} – ${e.getDate()} ${TH_MONTHS[e.getMonth()].slice(0,3)} ${year+543}`; })()
    : (() => { const d=new Date(selDay+'T00:00:00');
        return `${d.getDate()} ${TH_MONTHS[d.getMonth()]} ${d.getFullYear()+543}`; })();

  const onSave = (saved, isNew) => {
    setModal(null);
    // reload month
    load(year, month);
  };
  const onDelete = () => { setModal(null); load(year,month); };

  const onDayClick = (dateStr) => {
    if (viewMode==='month') { setSelDay(dateStr); setViewMode('day'); }
    else setModal({ type:'add', date: dateStr });
  };

  const onEventClick = (ev) => {
    if (ev._type==='due_date') return; // due date read-only
    setModal({ type:'edit', ev });
  };

  return (
    <div className="fade-in">
      <PageHead title="ปฏิทินกิจกรรม" sub="งานครบกำหนด ประชุม นัดหมาย และกิจกรรมต่างๆ">
        <button className="btn btn-primary" onClick={()=>setModal({type:'add',date:selDay})}>
          <Icon name="plus" style={{width:15,height:15}}/> เพิ่มกิจกรรม
        </button>
      </PageHead>

      {/* legend */}
      <div style={{display:'flex',gap:8,flexWrap:'wrap',marginBottom:14,alignItems:'center'}}>
        {Object.entries(CAL_TYPES).map(([k,t])=>(
          <div key={k} style={{display:'flex',alignItems:'center',gap:5,fontSize:11}}>
            <div style={{width:8,height:8,borderRadius:2,background:t.color}}/>
            <span style={{color:'var(--ink-2)'}}>{t.label}</span>
          </div>
        ))}
      </div>

      {/* toolbar */}
      <div className="card card-pad" style={{marginBottom:12}}>
        <div style={{display:'flex',alignItems:'center',gap:10,flexWrap:'wrap'}}>
          {/* nav buttons */}
          <button className="icon-btn" onClick={viewMode==='month'?prevMonth:viewMode==='week'?prevWeek:prevDay}>
            <Icon name="chevL" style={{width:18,height:18}}/>
          </button>
          <span style={{fontWeight:600,fontSize:15,minWidth:220,textAlign:'center'}}>{headerLabel}</span>
          <button className="icon-btn" onClick={viewMode==='month'?nextMonth:viewMode==='week'?nextWeek:nextDay}>
            <Icon name="chevR" style={{width:18,height:18}}/>
          </button>
          <button className="btn btn-ghost btn-sm" onClick={()=>{setYear(now.getFullYear());setMonth(now.getMonth()+1);setSelDay(today);}}>
            วันนี้
          </button>
          <div style={{marginLeft:'auto'}}>
            <div className="seg">
              {[['day','รายวัน'],['week','รายสัปดาห์'],['month','รายเดือน']].map(([v,l])=>(
                <button key={v} className={viewMode===v?'active':''} onClick={()=>setViewMode(v)}>{l}</button>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* calendar grid */}
      <div className="card" style={{overflow:'hidden'}}>
        {loading ? <div style={{padding:60,textAlign:'center'}}><LoadingSpinner/></div> : (
          viewMode==='month' ? (
            <MonthView year={year} month={month} allEvents={allEvents}
              onDayClick={onDayClick} onEventClick={onEventClick}/>
          ) : viewMode==='week' ? (
            <WeekView year={year} month={month} weekStart={weekStart} allEvents={allEvents}
              onDayClick={onDayClick} onEventClick={onEventClick}/>
          ) : (
            <DayView dateStr={selDay} allEvents={allEvents}
              onAddClick={()=>setModal({type:'add',date:selDay})} onEventClick={onEventClick}/>
          )
        )}
      </div>

      {/* modals */}
      {modal?.type==='add' && (
        <EventModal
          initial={{ event_type:'meeting', event_date: modal.date||today }}
          officers={officers}
          onSave={onSave} onDelete={onDelete} onClose={()=>setModal(null)}
        />
      )}
      {modal?.type==='edit' && (
        <EventModal
          initial={modal.ev}
          officers={officers}
          onSave={onSave} onDelete={onDelete} onClose={()=>setModal(null)}
        />
      )}
    </div>
  );
}

Object.assign(window, { CalendarPage });
