/* ============================================================
   admin-directors.jsx — แดชบอร์ดผู้บริหาร
   ผอ.กลุ่มนิติการ (ติดตามการสอบสวน) · ผอ.สำนักอำนวยการ (ภาพรวม)
   ============================================================ */

/* ---------------- Charts ---------------- */
function GroupedBars({ data }) {
  const max = Math.max(...data.flatMap(d=>[d.a,d.b]));
  return (
    <div className="bars" style={{height:170}}>
      {data.map((d,i)=>(
        <div key={i} className="b">
          <div style={{display:"flex",gap:5,alignItems:"flex-end",width:"100%",justifyContent:"center",height:"100%"}}>
            <div className="bar" style={{height:(d.a/max*100)+"%",background:"var(--maroon)",maxWidth:18}} title={"รับเข้า "+d.a}></div>
            <div className="bar" style={{height:(d.b/max*100)+"%",background:"var(--gold)",maxWidth:18}} title={"เสร็จสิ้น "+d.b}></div>
          </div>
          <div className="bl">{d.l}</div>
        </div>
      ))}
    </div>
  );
}

function Donut({ segments, total, label }) {
  const R=52, C=2*Math.PI*R; let off=0;
  return (
    <div style={{display:"flex",alignItems:"center",gap:24}}>
      <svg width="140" height="140" viewBox="0 0 140 140" style={{flex:"none"}}>
        <circle cx="70" cy="70" r={R} fill="none" stroke="var(--surface-3)" strokeWidth="18"/>
        {segments.map((s,i)=>{
          const len = s.v/total*C;
          const el = <circle key={i} cx="70" cy="70" r={R} fill="none" stroke={s.c} strokeWidth="18"
            strokeDasharray={`${len} ${C-len}`} strokeDashoffset={-off} transform="rotate(-90 70 70)" strokeLinecap="butt"/>;
          off += len; return el;
        })}
        <text x="70" y="65" textAnchor="middle" fontSize="26" fontWeight="700" fill="var(--ink)" style={{fontFamily:"var(--fs)"}}>{total}</text>
        <text x="70" y="85" textAnchor="middle" fontSize="11" fill="var(--ink-3)" style={{fontFamily:"var(--fs)"}}>{label}</text>
      </svg>
      <div className="legend">
        {segments.map((s,i)=>(
          <div key={i} className="lg"><span className="sw" style={{background:s.c}}></span><span style={{flex:1}}>{s.l}</span><b className="tnum">{s.v}</b></div>
        ))}
      </div>
    </div>
  );
}

/* ---------------- ผอ.กลุ่มนิติการ — ติดตามการสอบสวน ---------------- */
function DirLegalDashboard({ cases, officers, openCase, setView }) {
  const active = cases.filter(c=>["assigned","investigating","reporting"].includes(c.status));
  const slaOrder = {r:0,a:1,g:2};
  const monitor = [...cases].filter(c=>c.status!=="closed"&&c.status!=="received"&&c.status!=="screening")
    .sort((a,b)=>slaOrder[a.sla]-slaOrder[b.sla] || b.progress-a.progress);
  const cnt=(f)=>cases.filter(f).length;
  const byOfficer = (officers||[]).map(o=>({...o, cases:cases.filter(c=>c.assignee===o.id && c.status!=="closed")}));

  return (
    <div className="fade-in">
      <PageHead title="ติดตามการสอบสวน — กลุ่มนิติการ" sub="กำกับความคืบหน้าและกำหนดเวลาของแต่ละสำนวน ตามสายงานวินัยและกฎหมาย">
        <button className="btn btn-outline" onClick={()=>setView("cases")}><Icon name="inbox" style={{width:16,height:16}}/> สำนวนทั้งหมด</button>
      </PageHead>

      <div className="grid" style={{gridTemplateColumns:"repeat(4,1fr)",marginBottom:22}}>
        <StatCard ic="layers" lbl="สำนวนในกลุ่ม (ดำเนินการ)" num={active.length} sub="ทั้งสายวินัยและกฎหมาย" tone="maroon"/>
        <StatCard ic="clock" lbl="อยู่ระหว่างสอบสวน" num={cnt(c=>c.status==="investigating")} sub="นิติกรกำลังดำเนินการ" tone="info"/>
        <StatCard ic="alert" lbl="เกินกำหนด SLA" num={cnt(c=>c.sla==="r"&&c.status!=="closed")} sub="ต้องเร่งรัดติดตาม" tone="danger"/>
        <StatCard ic="forward" lbl="รอเสนอ/อนุมัติ" num={cnt(c=>c.status==="reporting")} sub="รอเสนอตามลำดับชั้น" tone="warn"/>
      </div>

      <div className="grid" style={{gridTemplateColumns:"1.7fr 1fr",alignItems:"start"}}>
        <div className="card">
          <div className="card-h"><h3>เฝ้าระวังกำหนดเวลา (SLA Monitor)</h3><span className="tiny faint">เรียงตามความเสี่ยง</span></div>
          <div className="card-pad" style={{display:"flex",flexDirection:"column",gap:0}}>
            {monitor.map((c,i)=>{
              const o=officerById(officers, c.assignee);
              return (
                <div key={c.id} onClick={()=>openCase(c.id)} style={{cursor:"pointer",padding:"14px 0",borderBottom:i<monitor.length-1?"1px solid var(--line)":"none"}}>
                  <div className="between" style={{marginBottom:7}}>
                    <div className="vcenter" style={{gap:9}}>
                      <span className="code sm">{c.id}</span>
                      <span className="badge badge-maroon" style={{fontSize:11}}>{TRACKS[c.track]?.label||c.track}</span>
                    </div>
                    <SLAText s={c.sla}/>
                  </div>
                  <div className="sm" style={{fontWeight:500,marginBottom:8,whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis"}}>{c.subject}</div>
                  <div className="progress"><i style={{width:c.progress+"%",background:c.sla==="r"?"var(--sla-r)":c.sla==="a"?"var(--sla-a)":"var(--maroon)"}}></i></div>
                  <div className="between tiny muted" style={{marginTop:6}}>
                    <span className="vcenter" style={{gap:6}}>{o&&<span className="avatar avatar-sm">{o.init}</span>}{o?o.name:"ยังไม่มอบหมาย"}</span>
                    <span>{c.progress}% · ครบกำหนด {thDate(c.due)}</span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        <div className="grid" style={{gap:16}}>
          <div className="card">
            <div className="card-h"><h3>ภาระงานนิติกร</h3></div>
            <div className="card-pad" style={{display:"flex",flexDirection:"column",gap:14}}>
              {byOfficer.map(o=>(
                <div key={o.id}>
                  <div className="between" style={{marginBottom:6}}>
                    <div className="vcenter" style={{gap:9}}><span className="avatar avatar-sm">{o.init}</span><div><div className="sm" style={{fontWeight:600}}>{o.name}</div><div className="faint tiny">{o.group.replace("กลุ่มงาน","")}</div></div></div>
                    <span className="badge badge-maroon">{o.cases.length} เรื่อง</span>
                  </div>
                  <div className="progress" style={{height:6}}><i style={{width:Math.min(o.cases.length/6*100,100)+"%"}}></i></div>
                </div>
              ))}
            </div>
          </div>
          <div className="card card-pad">
            <h3 style={{fontSize:15,marginBottom:14}}>สัดส่วนตามสายงาน</h3>
            <Donut total={cases.length} label="สำนวน" segments={[
              {l:"ด้านวินัย",v:cases.filter(c=>c.track==="discipline").length,c:"var(--maroon)"},
              {l:"ด้านกฎหมาย",v:cases.filter(c=>c.track==="legal").length,c:"var(--gold)"},
            ]}/>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---------------- ผอ.สำนักอำนวยการ — ภาพรวม ---------------- */
function DirAdminDashboard({ cases, officers, setView }) {
  const total = cases.length;
  const closed = cases.filter(c=>c.status==="closed").length;
  const onTime = cases.filter(c=>c.sla==="g").length;
  const overdue = cases.filter(c=>c.sla==="r"&&c.status!=="closed").length;
  const pending = cases.filter(c=>c.status!=="closed").length;
  const compliance = Math.round(onTime/total*100);
  const monthly = [
    {l:"ธ.ค.",a:14,b:11},{l:"ม.ค.",a:18,b:13},{l:"ก.พ.",a:15,b:14},
    {l:"มี.ค.",a:22,b:16},{l:"เม.ย.",a:19,b:17},{l:"พ.ค.",a:24,b:15},
  ];
  const slaR=cases.filter(c=>c.sla==="r"&&c.status!=="closed").length;
  const slaA=cases.filter(c=>c.sla==="a"&&c.status!=="closed").length;
  const slaG=cases.filter(c=>c.sla==="g").length;

  return (
    <div className="fade-in">
      <PageHead title="ภาพรวมการดำเนินงาน — สำนักอำนวยการ" sub="ภาพรวมผลการปฏิบัติงานของกลุ่มนิติการ ประจำปีงบประมาณ 2568">
        <button className="btn btn-outline"><Icon name="download" style={{width:16,height:16}}/> ส่งออกรายงาน (PDF/CSV)</button>
      </PageHead>

      <div className="grid" style={{gridTemplateColumns:"repeat(4,1fr)",marginBottom:22}}>
        <StatCard ic="layers" lbl="เรื่องรับเข้าทั้งหมด" num={total} sub="ปีงบประมาณ 2568" tone="maroon"/>
        <StatCard ic="shieldCheck" lbl="ดำเนินการตามกำหนด" num={compliance+"%"} sub={`${onTime} จาก ${total} เรื่อง`} tone="ok"/>
        <StatCard ic="clock" lbl="เรื่องคงค้าง" num={pending} sub="อยู่ระหว่างดำเนินการ" tone="info"/>
        <StatCard ic="alert" lbl="เกินกำหนด" num={overdue} sub="ต้องกำกับติดตาม" tone="danger"/>
      </div>

      <div className="grid" style={{gridTemplateColumns:"1.5fr 1fr",alignItems:"start",marginBottom:18}}>
        <div className="card card-pad">
          <div className="between" style={{marginBottom:18}}>
            <h3 style={{fontSize:16}}>เรื่องรับเข้าและเสร็จสิ้นรายเดือน</h3>
            <div className="vcenter" style={{gap:14}}>
              <span className="vcenter tiny"><span className="sw" style={{width:11,height:11,borderRadius:3,background:"var(--maroon)",display:"inline-block"}}></span> รับเข้า</span>
              <span className="vcenter tiny"><span className="sw" style={{width:11,height:11,borderRadius:3,background:"var(--gold)",display:"inline-block"}}></span> เสร็จสิ้น</span>
            </div>
          </div>
          <GroupedBars data={monthly}/>
        </div>
        <div className="card card-pad">
          <h3 style={{fontSize:16,marginBottom:18}}>สัดส่วนตามสายงาน</h3>
          <Donut total={total} label="เรื่อง" segments={[
            {l:"ด้านวินัย",v:cases.filter(c=>c.track==="discipline").length,c:"var(--maroon)"},
            {l:"ด้านกฎหมาย",v:cases.filter(c=>c.track==="legal").length,c:"var(--gold)"},
          ]}/>
        </div>
      </div>

      <div className="grid" style={{gridTemplateColumns:"1fr 1.5fr",alignItems:"start"}}>
        <div className="card card-pad">
          <h3 style={{fontSize:16,marginBottom:16}}>สถานะกำหนดเวลา (SLA)</h3>
          <div style={{display:"flex",flexDirection:"column",gap:14}}>
            {[["ตามกำหนด",slaG,"var(--sla-g)"],["ใกล้ครบกำหนด",slaA,"var(--sla-a)"],["เกินกำหนด",slaR,"var(--sla-r)"]].map(([l,v,c])=>(
              <div key={l}>
                <div className="between" style={{marginBottom:6}}><span className="sla" style={{color:c}}>{l}</span><b className="tnum">{v} เรื่อง</b></div>
                <div className="progress" style={{height:9}}><i style={{width:(v/total*100)+"%",background:c}}></i></div>
              </div>
            ))}
          </div>
          <hr className="hr" style={{margin:"18px 0"}}/>
          <div className="notice notice-ok"><Icon name="trend"/><div>อัตราการดำเนินการตามกำหนดอยู่ที่ <b>{compliance}%</b> สูงกว่าค่าเป้าหมาย 80%</div></div>
        </div>

        <div className="card">
          <div className="card-h"><h3>ภาระงานแยกตามกลุ่มงาน</h3><button className="btn btn-ghost btn-sm" onClick={()=>setView("cases")}>ดูสำนวน <Icon name="chevR" style={{width:14,height:14}}/></button></div>
          <div className="table-wrap">
            <table className="tbl">
              <thead><tr><th>กลุ่มงาน</th><th>รับเข้า</th><th>ดำเนินการ</th><th>เสร็จสิ้น</th><th>ตามกำหนด</th></tr></thead>
              <tbody>
                {[["discipline","กลุ่มงานวินัย"],["legal","กลุ่มงานกฎหมายและระเบียบ"]].map(([t,name])=>{
                  const g=cases.filter(c=>c.track===t);
                  const gc=g.filter(c=>c.status==="closed").length;
                  const gd=g.filter(c=>c.status!=="closed").length;
                  const gg=g.filter(c=>c.sla==="g").length;
                  return (<tr key={t} style={{cursor:"default"}}>
                    <td style={{fontWeight:600}}>{name}</td>
                    <td className="tnum">{g.length}</td>
                    <td className="tnum">{gd}</td>
                    <td className="tnum">{gc}</td>
                    <td><span className="badge badge-ok">{Math.round(gg/g.length*100)}%</span></td>
                  </tr>);
                })}
              </tbody>
            </table>
          </div>
          <div className="card-pad" style={{paddingTop:16}}>
            <div className="notice notice-info"><Icon name="info"/><div>กลุ่มนิติการอยู่ภายใต้ <b>สำนักอำนวยการ</b> ประกอบด้วยกลุ่มงานวินัย และกลุ่มงานกฎหมายและระเบียบ มีนิติกรรับผิดชอบ {(officers||[]).length} คน</div></div>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { GroupedBars, Donut, DirLegalDashboard, DirAdminDashboard });
