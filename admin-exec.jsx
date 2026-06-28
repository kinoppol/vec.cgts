/* ============================================================
   admin-exec.jsx — Executive Dashboard (ผู้บริหาร)
   ใช้กับ role: dir_legal, dir_admin, deputy_secretary, secretary, admin
   ============================================================ */

const { useState, useEffect, useCallback } = React;

/* ── สีสำหรับ status ── */
const SLA_COLOR = { g:'var(--ok)', a:'var(--warn)', r:'var(--danger)' };
const STATUS_TH = {
  received:'รับเรื่อง', screening:'กลั่นกรอง', rejected:'ปฏิเสธ',
  case:'เปิดสำนวน', assigned:'มอบหมายแล้ว',
  investigating:'ตรวจข้อเท็จจริง', reporting:'รายงาน', closed:'ปิดเรื่อง',
};

/* ── KPI Card ── */
function KpiCard({ label, value, sub, color = 'var(--ink)', bg, icon, onClick }) {
  return (
    <div onClick={onClick} style={{
      background: bg || 'var(--surface)',
      borderRadius:12, padding:'18px 20px',
      boxShadow:'0 1px 4px rgba(0,0,0,.07)',
      cursor: onClick ? 'pointer' : 'default',
      transition:'transform .15s, box-shadow .15s',
      display:'flex', flexDirection:'column', gap:6,
    }}
    onMouseEnter={e=>{ if(onClick){ e.currentTarget.style.transform='translateY(-2px)'; e.currentTarget.style.boxShadow='0 4px 16px rgba(0,0,0,.12)'; }}}
    onMouseLeave={e=>{ e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow='0 1px 4px rgba(0,0,0,.07)'; }}>
      <div style={{fontSize:12,color:'var(--ink-3)',fontWeight:500}}>{label}</div>
      <div style={{fontSize:32,fontWeight:800,color,lineHeight:1}}>{value.toLocaleString()}</div>
      {sub && <div style={{fontSize:11,color:'var(--ink-3)'}}>{sub}</div>}
    </div>
  );
}

/* ── Horizontal Bar ── */
function HBar({ items, maxVal, colorFn }) {
  if (!items?.length) return <div className="muted sm" style={{padding:8}}>ไม่มีข้อมูล</div>;
  const mx = maxVal || Math.max(...items.map(x=>x.total), 1);
  return (
    <div style={{display:'flex',flexDirection:'column',gap:8}}>
      {items.map((x,i) => (
        <div key={i}>
          <div className="between" style={{fontSize:12,marginBottom:3}}>
            <span style={{flex:1,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap',paddingRight:8}}>{x.label}</span>
            <span style={{fontWeight:700,color:colorFn?.(x)||'var(--maroon)',flexShrink:0}}>{x.total}</span>
          </div>
          <div style={{height:8,background:'var(--surface-2)',borderRadius:4,overflow:'hidden'}}>
            <div style={{
              height:'100%', borderRadius:4,
              width: (x.total/mx*100)+'%',
              background: colorFn?.(x)||'var(--maroon)',
              transition:'width .5s',
            }}/>
          </div>
        </div>
      ))}
    </div>
  );
}

/* ── Donut Chart SVG ── */
function DonutChart({ slices, size = 140 }) {
  const r = 52, cx = 70, cy = 70;
  const circ = 2 * Math.PI * r;
  const total = slices.reduce((s,x)=>s+x.value,0) || 1;
  let offset = 0;
  return (
    <svg width={size} height={size} viewBox="0 0 140 140">
      <circle cx={cx} cy={cy} r={r} fill="none" stroke="var(--surface-2)" strokeWidth={22}/>
      {slices.map((s,i) => {
        const pct   = s.value / total;
        const dash  = pct * circ;
        const node  = (
          <circle key={i} cx={cx} cy={cy} r={r}
            fill="none" stroke={s.color} strokeWidth={22}
            strokeDasharray={`${dash} ${circ - dash}`}
            strokeDashoffset={-offset * circ / total * r * 2 * Math.PI / circ + circ * 0.25}
            style={{transform:'rotate(-90deg)',transformOrigin:'70px 70px'}}
            strokeLinecap="butt"
          />
        );
        offset += s.value;
        return node;
      })}
      <text x={cx} y={cy-6} textAnchor="middle" style={{fontSize:22,fontWeight:800,fill:'var(--ink)'}}>
        {total}
      </text>
      <text x={cx} y={cy+14} textAnchor="middle" style={{fontSize:10,fill:'var(--ink-3)'}}>
        รายการ
      </text>
    </svg>
  );
}

/* ── Bar Chart (monthly) ── */
function MonthlyBar({ data }) {
  if (!data?.length) return <div className="muted sm">ไม่มีข้อมูล</div>;
  const maxVal = Math.max(...data.map(x=>Math.max(x.received,x.closed)),1);
  const W = 100 / data.length;
  return (
    <div style={{display:'flex',alignItems:'flex-end',gap:2,height:100}}>
      {data.map((d,i) => (
        <div key={i} style={{flex:1,display:'flex',gap:1,alignItems:'flex-end',position:'relative'}} title={d.ym}>
          <div style={{flex:1,background:'var(--maroon)',opacity:.75,borderRadius:'2px 2px 0 0',
            height: (d.received/maxVal*90)+'px', minHeight: d.received?2:0}}/>
          <div style={{flex:1,background:'var(--ok)',opacity:.8,borderRadius:'2px 2px 0 0',
            height: (d.closed/maxVal*90)+'px', minHeight: d.closed?2:0}}/>
        </div>
      ))}
    </div>
  );
}

/* ── Officer Table ── */
function OfficerTable({ data }) {
  if (!data?.length) return <div className="muted sm">ไม่มีข้อมูล</div>;
  const maxTotal = Math.max(...data.map(x=>parseInt(x.total)||0),1);
  return (
    <div style={{overflowX:'auto'}}>
      <table className="tbl" style={{fontSize:13}}>
        <thead>
          <tr>
            <th>นิติกร</th>
            <th>กลุ่ม</th>
            <th style={{textAlign:'right'}}>รวม</th>
            <th style={{textAlign:'right',color:'var(--danger)'}}>เกินกำหนด</th>
            <th style={{textAlign:'right',color:'var(--warn)'}}>ครบวันนี้</th>
            <th style={{textAlign:'right',color:'var(--ok)'}}>ปิดแล้ว</th>
            <th style={{width:100}}>ภาระงาน</th>
          </tr>
        </thead>
        <tbody>
          {data.map((o,i) => {
            const total = parseInt(o.total)||0;
            const pct   = total/maxTotal*100;
            const color = parseInt(o.overdue)>0 ? 'var(--danger)' : parseInt(o.due_today)>0 ? 'var(--warn)' : 'var(--ok)';
            return (
              <tr key={i}>
                <td>
                  <div className="vcenter" style={{gap:8}}>
                    <span className="avatar avatar-sm" style={{background:'var(--maroon)',color:'#fff',fontSize:11}}>
                      {o.init||o.name?.[0]}
                    </span>
                    <span style={{fontWeight:500}}>{o.name}</span>
                  </div>
                </td>
                <td className="faint sm">{o.group_name||'—'}</td>
                <td style={{textAlign:'right',fontWeight:700}}>{total}</td>
                <td style={{textAlign:'right',color:'var(--danger)',fontWeight:parseInt(o.overdue)>0?700:400}}>
                  {parseInt(o.overdue)||0}
                </td>
                <td style={{textAlign:'right',color:'var(--warn)',fontWeight:parseInt(o.due_today)>0?700:400}}>
                  {parseInt(o.due_today)||0}
                </td>
                <td style={{textAlign:'right',color:'var(--ok)'}}>
                  {parseInt(o.closed)||0}
                </td>
                <td>
                  <div style={{height:6,background:'var(--surface-2)',borderRadius:3,overflow:'hidden'}}>
                    <div style={{height:'100%',width:pct+'%',background:color,borderRadius:3,transition:'width .5s'}}/>
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

/* ── Aging Table ── */
function AgingSection({ aging }) {
  if (!aging) return null;
  const bands = [
    { label:'0–15 วัน',  key:'d0_15',   color:'var(--ok)' },
    { label:'16–30 วัน', key:'d16_30',  color:'#84cc16' },
    { label:'31–60 วัน', key:'d31_60',  color:'var(--warn)' },
    { label:'61–90 วัน', key:'d61_90',  color:'#f97316' },
    { label:'> 90 วัน',  key:'d90plus', color:'var(--danger)' },
  ];
  const total = bands.reduce((s,b)=>s+(parseInt(aging[b.key])||0),0)||1;
  return (
    <div style={{display:'flex',flexDirection:'column',gap:10}}>
      {bands.map((b,i) => {
        const val = parseInt(aging[b.key])||0;
        const pct = (val/total*100).toFixed(1);
        return (
          <div key={i}>
            <div className="between" style={{fontSize:12,marginBottom:3}}>
              <span style={{color:b.color,fontWeight:600}}>{b.label}</span>
              <span style={{fontWeight:700}}>{val} <span className="faint" style={{fontWeight:400}}>({pct}%)</span></span>
            </div>
            <div style={{height:10,background:'var(--surface-2)',borderRadius:5,overflow:'hidden'}}>
              <div style={{height:'100%',width:pct+'%',background:b.color,borderRadius:5,transition:'width .6s'}}/>
            </div>
          </div>
        );
      })}
    </div>
  );
}

/* ── Main Dashboard ── */
function ExecDashboard({ currentUser }) {
  const [data,    setData]    = useState(null);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState(null);
  const [refresh, setRefresh] = useState(0);

  useEffect(() => {
    setLoading(true); setError(null);
    api.getExecDashboard()
      .then(setData)
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, [refresh]);

  if (loading) return <LoadingSpinner/>;
  if (error)   return <div className="notice notice-danger" style={{margin:24}}>{error}</div>;
  if (!data)   return null;

  const { kpi, by_officer, by_track, by_cat, by_agency, aging, by_status, monthly, sla_summary } = data;

  /* sla donut */
  const slaSlices = [
    { label:'ทันกำหนด',  value: parseInt(sla_summary.find(x=>x.sla==='g')?.total)||0, color:'var(--ok)' },
    { label:'ใกล้ครบ',   value: parseInt(sla_summary.find(x=>x.sla==='a')?.total)||0, color:'var(--warn)' },
    { label:'เกินกำหนด', value: parseInt(sla_summary.find(x=>x.sla==='r')?.total)||0, color:'var(--danger)' },
  ];

  /* track donut */
  const trackColors = { discipline:'var(--maroon)', legal:'var(--info)' };
  const trackSlices = by_track.map(t => ({
    label: t.track==='discipline'?'ด้านวินัย':'ด้านกฎหมาย',
    value: parseInt(t.total)||0,
    color: trackColors[t.track]||'#888',
  }));

  const genTime = new Date(data.generated_at).toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'});

  return (
    <div className="fade-in" style={{paddingBottom:32}}>
      <PageHead
        title="Dashboard ผู้บริหาร"
        sub={`สรุปภาพรวมงานนิติการ · อัปเดตล่าสุด ${genTime} น.`}
      >
        <button className="btn btn-ghost btn-sm" onClick={()=>setRefresh(r=>r+1)}>
          <Icon name="refresh" style={{width:14,height:14}}/> รีเฟรช
        </button>
      </PageHead>

      {/* ── KPI Row 1: งานสำคัญ ── */}
      <div style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(150px,1fr))',gap:12,marginBottom:16}}>
        <KpiCard label="งานทั้งหมด (active)" value={kpi.total} color="var(--ink)"/>
        <KpiCard label="ครบกำหนดวันนี้" value={kpi.due_today}
          color={kpi.due_today>0?'var(--warn)':'var(--ok)'}
          bg={kpi.due_today>0?'rgba(245,158,11,.06)':undefined}/>
        <KpiCard label="เกินกำหนด" value={kpi.overdue}
          color={kpi.overdue>0?'var(--danger)':'var(--ok)'}
          bg={kpi.overdue>0?'rgba(220,38,38,.06)':undefined}/>
        <KpiCard label="ยังไม่เริ่ม" value={kpi.not_started}
          color={kpi.not_started>0?'var(--ink-2)':'var(--ok)'}/>
        <KpiCard label="ปิดเดือนนี้" value={kpi.closed_month} color="var(--ok)"/>
      </div>

      {/* ── KPI Row 2: งานค้าง ── */}
      <div style={{display:'grid',gridTemplateColumns:'repeat(3,1fr)',gap:12,marginBottom:24}}>
        <KpiCard label="ค้างเกิน 30 วัน" value={kpi.pending30}
          color={kpi.pending30>0?'#f97316':'var(--ok)'}
          bg={kpi.pending30>0?'rgba(249,115,22,.06)':undefined}/>
        <KpiCard label="ค้างเกิน 60 วัน" value={kpi.pending60}
          color={kpi.pending60>0?'#ea580c':'var(--ok)'}
          bg={kpi.pending60>0?'rgba(234,88,12,.06)':undefined}/>
        <KpiCard label="ค้างเกิน 90 วัน" value={kpi.pending90}
          color={kpi.pending90>0?'var(--danger)':'var(--ok)'}
          bg={kpi.pending90>0?'rgba(220,38,38,.08)':undefined}/>
      </div>

      {/* ── Row: Officer table ── */}
      <div className="card" style={{marginBottom:16}}>
        <div style={{padding:'14px 20px',borderBottom:'1px solid var(--line)',display:'flex',alignItems:'center',justifyContent:'space-between'}}>
          <h3 style={{margin:0,fontSize:15}}>งานตามนิติกร</h3>
          <span className="badge badge-maroon">{by_officer.length} คน</span>
        </div>
        <div style={{padding:16}}>
          <OfficerTable data={by_officer}/>
        </div>
      </div>

      {/* ── Row: SLA + Track + Aging ── */}
      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:16,marginBottom:16}}>

        {/* SLA */}
        <div className="card" style={{padding:20}}>
          <h4 style={{margin:'0 0 16px',fontSize:14}}>สถานะ SLA</h4>
          <div style={{display:'flex',flexDirection:'column',alignItems:'center',gap:16}}>
            <DonutChart slices={slaSlices}/>
            <div style={{width:'100%',display:'flex',flexDirection:'column',gap:8}}>
              {slaSlices.map((s,i)=>(
                <div key={i} className="between" style={{fontSize:12}}>
                  <div className="vcenter" style={{gap:6}}>
                    <div style={{width:10,height:10,borderRadius:'50%',background:s.color,flexShrink:0}}/>
                    <span>{s.label}</span>
                  </div>
                  <span style={{fontWeight:700,color:s.color}}>{s.value}</span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Track */}
        <div className="card" style={{padding:20}}>
          <h4 style={{margin:'0 0 16px',fontSize:14}}>งานตามสายงาน</h4>
          <div style={{display:'flex',flexDirection:'column',alignItems:'center',gap:16}}>
            <DonutChart slices={trackSlices}/>
            <div style={{width:'100%',display:'flex',flexDirection:'column',gap:8}}>
              {trackSlices.map((s,i)=>(
                <div key={i} className="between" style={{fontSize:12}}>
                  <div className="vcenter" style={{gap:6}}>
                    <div style={{width:10,height:10,borderRadius:'50%',background:s.color,flexShrink:0}}/>
                    <span>{s.label}</span>
                  </div>
                  <div className="vcenter" style={{gap:4}}>
                    <span style={{fontWeight:700}}>{s.value}</span>
                    <span className="faint" style={{fontSize:11}}>
                      ({(s.value/(by_track.reduce((a,x)=>a+parseInt(x.total),0)||1)*100).toFixed(0)}%)
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Aging */}
        <div className="card" style={{padding:20}}>
          <h4 style={{margin:'0 0 16px',fontSize:14}}>อายุเรื่อง (Case Aging)</h4>
          <AgingSection aging={aging}/>
        </div>
      </div>

      {/* ── Row: By category + By agency ── */}
      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:16,marginBottom:16}}>
        <div className="card" style={{padding:20}}>
          <h4 style={{margin:'0 0 16px',fontSize:14}}>งานตามประเภท / หมวดหมู่</h4>
          <HBar
            items={by_cat.map(x=>({label:x.cat,total:parseInt(x.total)}))}
            colorFn={()=>'var(--maroon)'}
          />
        </div>
        <div className="card" style={{padding:20}}>
          <h4 style={{margin:'0 0 16px',fontSize:14}}>งานตามหน่วยงาน / จังหวัด (Top 20)</h4>
          <HBar
            items={by_agency.map(x=>({label:x.agency,total:parseInt(x.total)}))}
            colorFn={()=>'var(--info)'}
          />
        </div>
      </div>

      {/* ── Row: Monthly trend + Status breakdown ── */}
      <div style={{display:'grid',gridTemplateColumns:'2fr 1fr',gap:16}}>
        <div className="card" style={{padding:20}}>
          <div className="between" style={{marginBottom:12}}>
            <h4 style={{margin:0,fontSize:14}}>แนวโน้มรายเดือน (12 เดือนล่าสุด)</h4>
            <div className="vcenter" style={{gap:12,fontSize:11}}>
              <div className="vcenter" style={{gap:4}}>
                <div style={{width:10,height:10,borderRadius:2,background:'var(--maroon)',opacity:.75}}/>
                <span>รับเรื่อง</span>
              </div>
              <div className="vcenter" style={{gap:4}}>
                <div style={{width:10,height:10,borderRadius:2,background:'var(--ok)',opacity:.8}}/>
                <span>ปิดเรื่อง</span>
              </div>
            </div>
          </div>
          <MonthlyBar data={monthly}/>
          <div style={{display:'flex',justifyContent:'space-between',marginTop:4,fontSize:10,color:'var(--ink-3)'}}>
            {monthly.length > 0 && <span>{monthly[0]?.ym}</span>}
            {monthly.length > 1 && <span>{monthly[monthly.length-1]?.ym}</span>}
          </div>
        </div>

        <div className="card" style={{padding:20}}>
          <h4 style={{margin:'0 0 14px',fontSize:14}}>สถานะทั้งหมด</h4>
          <div style={{display:'flex',flexDirection:'column',gap:10}}>
            {by_status.map((s,i)=>{
              const total2 = by_status.reduce((a,x)=>a+parseInt(x.total),0)||1;
              const pct = (parseInt(s.total)/total2*100).toFixed(0);
              return (
                <div key={i}>
                  <div className="between" style={{fontSize:12,marginBottom:3}}>
                    <span>{STATUS_TH[s.status]||s.status}</span>
                    <span style={{fontWeight:700}}>{parseInt(s.total)}</span>
                  </div>
                  <div style={{height:6,background:'var(--surface-2)',borderRadius:3,overflow:'hidden'}}>
                    <div style={{height:'100%',width:pct+'%',background:'var(--maroon)',opacity:.7,borderRadius:3}}/>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}
