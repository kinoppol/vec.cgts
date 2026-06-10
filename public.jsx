/* ============================================================
   public.jsx — ฝั่งประชาชน: หน้าแรก, แบบฟอร์ม, ติดตามสถานะ
   ============================================================ */

function ThemeToggle({ theme, setTheme }) {
  const opts = [["light","sun"],["dark","moon"],["system","monitor"]];
  return (
    <div className="seg" role="group" aria-label="โหมดแสดงผล">
      {opts.map(([v,ic]) => (
        <button key={v} className={theme===v?"active":""} onClick={()=>setTheme(v)} title={{light:"สว่าง",dark:"มืด",system:"ตามระบบ"}[v]}>
          <Icon name={ic} style={{width:15,height:15}} />
        </button>
      ))}
    </div>
  );
}

function PubHeader({ go, active, theme, setTheme }) {
  return (
    <header className="pub-header">
      <div className="container inner">
        <div className="pub-logo" style={{cursor:"pointer"}} onClick={()=>go("home")}>
          <img src="assets/ovec-logo.svg" alt="ตราสำนักงานคณะกรรมการการอาชีวศึกษา" />
          <div>
            <div className="t1">ระบบรับเรื่องร้องเรียน–ร้องทุกข์</div>
            <div className="t2">สำนักงานคณะกรรมการการอาชีวศึกษา · กลุ่มนิติการ</div>
          </div>
        </div>
        <nav className="pub-nav">
          <a className={active==="home"?"":""} onClick={()=>go("home")}>หน้าแรก</a>
          <a onClick={()=>go("track")}>ติดตามสถานะ</a>
          <ThemeToggle theme={theme} setTheme={setTheme} />
          <button className="btn btn-primary btn-sm" onClick={()=>go("form")} style={{marginLeft:6}}>
            <Icon name="filePlus" style={{width:16,height:16}}/> ยื่นเรื่อง
          </button>
          <button className="btn btn-outline btn-sm" onClick={()=>go("login")}>
            <Icon name="lock" style={{width:15,height:15}}/> เจ้าหน้าที่
          </button>
        </nav>
      </div>
    </header>
  );
}

function PubFooter() {
  return (
    <footer className="footer">
      <div className="container between" style={{alignItems:"flex-start",flexWrap:"wrap",gap:24}}>
        <div className="pub-logo">
          <img src="assets/ovec-logo.svg" style={{width:42,height:42}} alt=""/>
          <div>
            <div className="t1" style={{fontSize:14}}>สำนักงานคณะกรรมการการอาชีวศึกษา</div>
            <div className="t2">ถนนราชดำเนินนอก เขตดุสิต กรุงเทพมหานคร 10300</div>
          </div>
        </div>
        <div style={{maxWidth:420}}>
          <div style={{fontWeight:600,color:"var(--ink)",marginBottom:6}}>คุ้มครองข้อมูลส่วนบุคคล (PDPA)</div>
          ระบบจัดเก็บข้อมูลเท่าที่จำเป็นเพื่อการพิจารณาเรื่องร้องเรียน–ร้องทุกข์ ผู้ร้องสามารถเลือกไม่เปิดเผยตัวตนได้ ข้อมูลทั้งหมดถูกเข้ารหัสและเข้าถึงได้เฉพาะเจ้าหน้าที่ผู้รับผิดชอบตามสิทธิ์
        </div>
      </div>
      <div className="container" style={{marginTop:24,paddingTop:18,borderTop:"1px solid var(--line)",fontSize:12.5}} >
        © {new Date().getFullYear() + 543} สำนักงานคณะกรรมการการอาชีวศึกษา · ระบบบริหารจัดการงานนิติการ
      </div>
    </footer>
  );
}

/* ---------------- หน้าแรก ---------------- */
function PublicHome({ go }) {
  const features = [
    { ic:"shieldCheck", t:"ยื่นได้อย่างมั่นใจ", d:"เลือกยืนยันตัวตนด้วยบัญชี Google เพื่อความน่าเชื่อถือ หรือยื่นแบบไม่ประสงค์ออกนามก็ได้" },
    { ic:"lock", t:"ปลอดภัยตามมาตรฐาน", d:"ข้อมูลและไฟล์แนบถูกเข้ารหัส ป้องกันสแปม และเข้าถึงได้เฉพาะเจ้าหน้าที่ผู้รับผิดชอบ" },
    { ic:"search", t:"ติดตามได้ทุกขั้นตอน", d:"รับรหัสติดตาม (Ticket) ทันทีหลังยื่นเรื่อง ตรวจสอบสถานะการดำเนินงานได้ตลอดเวลา" },
  ];
  const process = [
    { t:"ยื่นเรื่อง", d:"กรอกแบบฟอร์มและแนบหลักฐาน ยืนยันความยินยอม PDPA" },
    { t:"รับเรื่อง & คัดกรอง", d:"กลุ่มนิติการตรวจสอบและลงทะเบียนเลขรับเรื่อง" },
    { t:"แปลงเป็นสำนวน", d:"มอบหมายนิติกรเจ้าของเรื่องตามสายงานวินัย/กฎหมาย" },
    { t:"พิจารณา & แจ้งผล", d:"ดำเนินการตามระเบียบ ติดตามกำหนดเวลา และแจ้งผลกลับ" },
  ];
  return (
    <div className="fade-in">
      {/* hero */}
      <section className="hero">
        <img className="crest" src="assets/ovec-logo.svg" alt=""/>
        <div className="container hero-in">
          <span className="eyebrow"><Icon name="shield" style={{width:15,height:15}}/> ช่องทางราชการอย่างเป็นทางการ</span>
          <h1>แจ้งเรื่องร้องเรียน และร้องทุกข์<br/>ต่อสำนักงานคณะกรรมการการอาชีวศึกษา</h1>
          <p>ช่องทางกลางสำหรับประชาชนและบุคลากร ในการแจ้งเรื่องร้องเรียน ร้องทุกข์ และแจ้งเบาะแส โปร่งใส ตรวจสอบได้ และคุ้มครองข้อมูลผู้ร้องตามกฎหมาย</p>
          <div className="hero-cta">
            <button className="btn btn-primary btn-lg" onClick={()=>go("form")}>
              <Icon name="filePlus"/> ยื่นเรื่องร้องเรียน–ร้องทุกข์
            </button>
            <button className="btn btn-outline btn-lg" onClick={()=>go("track")}>
              <Icon name="search"/> ติดตามสถานะด้วยรหัส
            </button>
          </div>
        </div>
      </section>

      <div className="container" style={{padding:"48px 28px"}}>
        {/* features */}
        <div className="feat">
          {features.map((f,i)=>(
            <div key={i} className="card card-pad">
              <div className="fi"><Icon name={f.ic}/></div>
              <h3 style={{fontSize:17}}>{f.t}</h3>
              <p className="muted sm">{f.d}</p>
            </div>
          ))}
        </div>

        {/* process */}
        <div style={{marginTop:56}}>
          <div style={{textAlign:"center",marginBottom:34}}>
            <span className="badge badge-maroon">ขั้นตอนการดำเนินงาน</span>
            <h2 style={{fontSize:26,marginTop:14}}>เรื่องของท่านดำเนินไปอย่างไร</h2>
            <p className="muted" style={{marginTop:8}}>ทุกเรื่องผ่านกระบวนการที่ได้มาตรฐานของกลุ่มนิติการ สอศ.</p>
          </div>
          <div className="grid" style={{gridTemplateColumns:"repeat(4,1fr)"}}>
            {process.map((s,i)=>(
              <div key={i} className="card card-pad" style={{display:"flex",flexDirection:"column",gap:12}}>
                <div className="process-step"><div className="pn">{i+1}</div></div>
                <h3 style={{fontSize:16}}>{s.t}</h3>
                <p className="muted sm">{s.d}</p>
              </div>
            ))}
          </div>
        </div>

        {/* CTA panel */}
        <div className="card" style={{marginTop:48,overflow:"hidden",display:"grid",gridTemplateColumns:"1.4fr 1fr"}}>
          <div className="card-pad" style={{padding:"38px 40px"}}>
            <h2 style={{fontSize:24}}>มีเรื่องที่ต้องการแจ้ง?</h2>
            <p className="muted" style={{marginTop:10,maxWidth:440}}>ใช้เวลาเพียงไม่กี่นาที ท่านสามารถเลือกเปิดเผยหรือไม่เปิดเผยตัวตนก็ได้ แต่ขอช่องทางติดต่อกลับเพื่อแจ้งความคืบหน้า</p>
            <div className="row" style={{marginTop:24}}>
              <button className="btn btn-primary btn-lg" onClick={()=>go("form")}><Icon name="filePlus"/> เริ่มยื่นเรื่อง</button>
            </div>
            <div className="vcenter" style={{marginTop:22,gap:18,flexWrap:"wrap"}}>
              <span className="vcenter tiny muted"><Icon name="shieldCheck" style={{width:16,height:16,color:"var(--ok)"}}/> เข้ารหัสปลอดภัย</span>
              <span className="vcenter tiny muted"><Icon name="user" style={{width:16,height:16,color:"var(--info)"}}/> ไม่ระบุตัวตนได้</span>
              <span className="vcenter tiny muted"><Icon name="clock" style={{width:16,height:16,color:"var(--maroon)"}}/> รับรหัสติดตามทันที</span>
            </div>
          </div>
          <div style={{background:"linear-gradient(150deg,var(--maroon-700),var(--maroon))",display:"grid",placeItems:"center",padding:30}}>
            <img src="assets/ovec-logo.svg" style={{width:170,height:170,filter:"drop-shadow(0 8px 24px rgba(0,0,0,.3))"}} alt=""/>
          </div>
        </div>
      </div>
      <PubFooter/>
    </div>
  );
}

/* ---------------- แบบฟอร์มยื่นเรื่อง ---------------- */
function ComplaintForm({ go }) {
  const [step, setStep] = useState(0);
  const [data, setData] = useState({
    identity:"", type:"", track:"", cat:"", subject:"", agency:"", detail:"",
    files:[], name:"", email:"", phone:"", pdpa:false, contactPref:"email",
  });
  const [ticket, setTicket] = useState("");
  const set = (k,v) => setData(d=>({...d,[k]:v}));
  const steps = ["วิธียื่น & ประเภท","รายละเอียดเรื่อง","ข้อมูลติดต่อ & ยินยอม","ทบทวนและยืนยัน"];

  const canNext = () => {
    if(step===0) return data.identity && data.type && data.track && data.cat;
    if(step===1) return data.subject.trim() && data.detail.trim().length>10;
    if(step===2) return data.email.trim() && data.pdpa && (data.identity==="anon" || data.name.trim());
    return true;
  };
  const [submitting, setSubmitting] = useState(false);
  const [submitErr, setSubmitErr] = useState("");
  const submit = async () => {
    setSubmitting(true); setSubmitErr("");
    try {
      const res = await api.createCase({ ...data });
      setTicket(res.id); setStep(4);
    } catch(e) {
      setSubmitErr(e.message || "เกิดข้อผิดพลาด กรุณาลองใหม่");
    } finally {
      setSubmitting(false);
    }
  };

  if(step===4) return <SubmitSuccess ticket={ticket} data={data} go={go} />;

  return (
    <div className="container fade-in" style={{maxWidth:780,padding:"34px 28px 80px"}}>
      <button className="btn btn-ghost btn-sm" onClick={()=>go("home")} style={{marginBottom:14}}><Icon name="chevL" style={{width:16,height:16}}/> กลับหน้าแรก</button>
      <h1 style={{fontSize:26}}>ยื่นเรื่องร้องเรียน–ร้องทุกข์</h1>
      <p className="muted" style={{marginTop:6,marginBottom:26}}>กรอกข้อมูลให้ครบถ้วนเพื่อให้เจ้าหน้าที่พิจารณาได้รวดเร็ว · ใช้เวลาประมาณ 5 นาที</p>

      {/* stepper */}
      <div className="stepper" style={{marginBottom:30}}>
        {steps.map((s,i)=>(
          <React.Fragment key={i}>
            <div className={"step "+(i===step?"active":i<step?"done":"")}>
              <div className="num">{i<step ? <Icon name="check" style={{width:15,height:15}}/> : i+1}</div>
              <div className="stt" style={{display:i===step?"block":"none"}}>{s}</div>
            </div>
            {i<steps.length-1 && <div className={"bar "+(i<step?"done":"")}></div>}
          </React.Fragment>
        ))}
      </div>

      <div className="card card-pad" style={{padding:28}}>
        {step===0 && <Step1 data={data} set={set} />}
        {step===1 && <Step2 data={data} set={set} />}
        {step===2 && <Step3 data={data} set={set} />}
        {step===3 && <Step4 data={data} />}
      </div>

      <div className="between" style={{marginTop:22}}>
        <button className="btn btn-outline" onClick={()=> step===0 ? go("home") : setStep(step-1)}>
          <Icon name="chevL" style={{width:16,height:16}}/> {step===0?"ยกเลิก":"ย้อนกลับ"}
        </button>
        {step<3
          ? <button className="btn btn-primary" disabled={!canNext()} onClick={()=>setStep(step+1)}>ถัดไป <Icon name="chevR" style={{width:16,height:16}}/></button>
          : <button className="btn btn-primary" disabled={!canNext()||submitting} onClick={submit}>
              {submitting ? <LoadingSpinner/> : <><Icon name="send" style={{width:16,height:16}}/> ยืนยันยื่นเรื่อง</>}
            </button>}
        {submitErr && <div className="notice notice-warn" style={{marginTop:10}}><Icon name="alert"/><div>{submitErr}</div></div>}
      </div>
    </div>
  );
}

function Step1({ data, set }) {
  const types = [
    {v:"complaint", t:"ร้องเรียน", d:"แจ้งเรื่องการกระทำที่ไม่ถูกต้อง ทุจริต หรือประพฤติมิชอบ"},
    {v:"grievance", t:"ร้องทุกข์", d:"ขอความเป็นธรรม หรือได้รับความเดือดร้อนจากการปฏิบัติงาน"},
    {v:"tip", t:"แจ้งเบาะแส", d:"ให้ข้อมูล/เบาะแสการกระทำผิด โดยอาจไม่ประสงค์ออกนาม"},
  ];
  return (
    <div className="grid" style={{gap:24}}>
      <div>
        <h3 style={{fontSize:16,marginBottom:4}}>1. เลือกวิธีการยื่นเรื่อง</h3>
        <p className="muted sm" style={{marginBottom:14}}>เพื่อความน่าเชื่อถือและลดสแปม แนะนำให้ยืนยันตัวตน แต่ท่านสามารถเลือกไม่ประสงค์ออกนามได้</p>
        <div className="choices" style={{gridTemplateColumns:"1fr 1fr"}}>
          <div className={"choice "+(data.identity==="google"?"active":"")} onClick={()=>set("identity","google")}>
            <span className="radio"></span>
            <Icon name="google" style={{width:20,height:20,flex:"none",marginTop:1}}/>
            <div style={{flex:1,minWidth:0}}><div className="ct">ยืนยันตัวตน</div><div className="cd">เข้าสู่ระบบด้วยบัญชี Google ของท่าน</div></div>
          </div>
          <div className={"choice "+(data.identity==="anon"?"active":"")} onClick={()=>set("identity","anon")}>
            <span className="radio"></span>
            <Icon name="user" style={{width:20,height:20,flex:"none",marginTop:1,color:"var(--maroon)"}}/>
            <div style={{flex:1,minWidth:0}}><div className="ct">ไม่ประสงค์ออกนาม</div><div className="cd">ไม่เปิดเผยชื่อ แต่ต้องระบุช่องทางติดต่อกลับ</div></div>
          </div>
        </div>
        {data.identity==="google" &&
          <div className="notice notice-ok" style={{marginTop:12}}><Icon name="checkCircle"/><div>จำลองการเข้าสู่ระบบสำเร็จในฐานะ <b>ผู้ใช้ที่ยืนยันตัวตน</b> — ข้อมูลบัญชีจะถูกผูกกับเรื่องโดยอัตโนมัติ</div></div>}
        {data.identity==="anon" &&
          <div className="notice notice-warn" style={{marginTop:12}}><Icon name="info"/><div>การยื่นแบบไม่ประสงค์ออกนามจะถูกตรวจสอบความน่าเชื่อถือเข้มข้นขึ้น และต้องผ่านการยืนยันว่าไม่ใช่บอท (CAPTCHA)</div></div>}
      </div>

      <hr className="hr"/>

      <div>
        <h3 style={{fontSize:16,marginBottom:14}}>2. ประเภทเรื่อง</h3>
        <div className="choices">
          {types.map(t=>(
            <div key={t.v} className={"choice "+(data.type===t.v?"active":"")} onClick={()=>set("type",t.v)}>
              <span className="radio"></span>
              <div><div className="ct">{t.t}</div><div className="cd">{t.d}</div></div>
            </div>
          ))}
        </div>
      </div>

      <div>
        <h3 style={{fontSize:16,marginBottom:6}}>3. สายงานและหมวดหมู่</h3>
        <p className="muted sm" style={{marginBottom:12}}>ช่วยให้ระบบส่งต่อไปยังกลุ่มงานที่เกี่ยวข้อง (เจ้าหน้าที่จะตรวจทานอีกครั้ง)</p>
        <div className="grid" style={{gridTemplateColumns:"1fr 1fr",gap:14}}>
          <div className="field">
            <label>สายงาน <span className="req">*</span></label>
            <select className="select" value={data.track} onChange={e=>{set("track",e.target.value);set("cat","");}}>
              <option value="">— เลือกสายงาน —</option>
              <option value="discipline">ด้านวินัย (กลุ่มงานวินัย)</option>
              <option value="legal">ด้านกฎหมาย (กลุ่มงานกฎหมายและระเบียบ)</option>
            </select>
          </div>
          <div className="field">
            <label>หมวดหมู่ <span className="req">*</span></label>
            <select className="select" value={data.cat} onChange={e=>set("cat",e.target.value)} disabled={!data.track}>
              <option value="">— เลือกหมวดหมู่ —</option>
              {data.track && TRACKS[data.track].cats.map(c=><option key={c} value={c}>{c}</option>)}
            </select>
          </div>
        </div>
      </div>
    </div>
  );
}

function Step2({ data, set }) {
  const addFiles = () => {
    const samples = [["หลักฐานประกอบ.pdf","320 KB"],["ภาพถ่าย.jpg","1.2 MB"],["เอกสารแนบ.pdf","210 KB"]];
    const s = samples[data.files.length % samples.length];
    set("files",[...data.files,{n:s[0],s:s[1]}]);
  };
  return (
    <div className="grid" style={{gap:18}}>
      <div className="field">
        <label>หัวข้อเรื่อง <span className="req">*</span></label>
        <input className="input" placeholder="สรุปเรื่องโดยย่อ เช่น ร้องเรียนการจัดซื้อจัดจ้างไม่โปร่งใส"
          value={data.subject} onChange={e=>set("subject",e.target.value)} />
      </div>
      <div className="field">
        <label>หน่วยงาน/สถานศึกษาที่เกี่ยวข้อง</label>
        <input className="input" placeholder="เช่น วิทยาลัยเทคนิค... / สำนัก... (ถ้าทราบ)"
          value={data.agency} onChange={e=>set("agency",e.target.value)} />
      </div>
      <div className="field">
        <label>รายละเอียดของเรื่อง <span className="req">*</span></label>
        <textarea className="textarea" placeholder="โปรดอธิบายเหตุการณ์ วันเวลา สถานที่ บุคคลที่เกี่ยวข้อง และข้อเท็จจริงให้ครบถ้วน"
          value={data.detail} onChange={e=>set("detail",e.target.value)} />
        <span className="help">ระบุรายละเอียดอย่างน้อย 10 ตัวอักษร · ปัจจุบัน {data.detail.length} ตัวอักษร</span>
      </div>
      <div className="field">
        <label>แนบไฟล์หลักฐาน</label>
        <div className="dropzone" onClick={addFiles}>
          <Icon name="paperclip" style={{width:24,height:24,color:"var(--maroon)",margin:"0 auto 8px"}}/>
          <div style={{fontWeight:600,fontSize:14}}>คลิกเพื่อแนบไฟล์ (จำลอง)</div>
          <div className="help" style={{marginTop:4}}>รองรับ PDF, JPG, PNG, ZIP ขนาดไม่เกิน 20 MB ต่อไฟล์ · ไฟล์จะถูกตรวจไวรัสและเข้ารหัส</div>
        </div>
        {data.files.length>0 &&
          <div className="grid" style={{gap:8,marginTop:10}}>
            {data.files.map((f,i)=>(
              <div key={i} className="file-row">
                <Icon name="file" style={{width:18,height:18,color:"var(--maroon)"}}/>
                <span style={{fontWeight:500}}>{f.n}</span>
                <span className="fmeta">{f.s}</span>
                <button className="icon-btn" style={{width:28,height:28,marginLeft:"auto"}} onClick={(e)=>{e.stopPropagation();set("files",data.files.filter((_,j)=>j!==i));}}>
                  <Icon name="x" style={{width:14,height:14}}/>
                </button>
              </div>
            ))}
          </div>}
      </div>
    </div>
  );
}

function Step3({ data, set }) {
  return (
    <div className="grid" style={{gap:18}}>
      {data.identity==="anon"
        ? <div className="notice notice-warn"><Icon name="user"/><div>ท่านเลือกยื่นแบบ <b>ไม่ประสงค์ออกนาม</b> — ไม่ต้องระบุชื่อ แต่ต้องให้ช่องทางติดต่อกลับเพื่อแจ้งผลและขอข้อมูลเพิ่มเติม (ข้อมูลติดต่อจะถูกปกปิดจากผู้ถูกร้อง)</div></div>
        : <div className="notice notice-info"><Icon name="shieldCheck"/><div>ข้อมูลของท่านจะถูกเก็บเป็นความลับ เปิดเผยเฉพาะเจ้าหน้าที่ผู้รับผิดชอบตามสิทธิ์เท่านั้น</div></div>}

      {data.identity!=="anon" &&
        <div className="field">
          <label>ชื่อ–นามสกุล ผู้ร้อง <span className="req">*</span></label>
          <input className="input" placeholder="ระบุชื่อจริง" value={data.name} onChange={e=>set("name",e.target.value)} />
        </div>}

      <div className="grid" style={{gridTemplateColumns:"1fr 1fr",gap:14}}>
        <div className="field">
          <label>อีเมลสำหรับติดต่อกลับ <span className="req">*</span></label>
          <input className="input" type="email" placeholder="you@email.com" value={data.email} onChange={e=>set("email",e.target.value)} />
          <span className="help">ใช้ยืนยันและติดตามสถานะเรื่อง</span>
        </div>
        <div className="field">
          <label>เบอร์โทรศัพท์</label>
          <input className="input" placeholder="08x-xxx-xxxx (ถ้ามี)" value={data.phone} onChange={e=>set("phone",e.target.value)} />
        </div>
      </div>

      <hr className="hr"/>
      <div className={"check "+(data.pdpa?"on":"")} onClick={()=>set("pdpa",!data.pdpa)} style={{alignItems:"flex-start"}}>
        <span className="box"><Icon name="check"/></span>
        <span>ข้าพเจ้ายินยอมให้สำนักงานคณะกรรมการการอาชีวศึกษาเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคลข้างต้น เพื่อวัตถุประสงค์ในการพิจารณาและดำเนินการเรื่องร้องเรียน–ร้องทุกข์เท่านั้น ตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 <span className="req">*</span></span>
      </div>
      {data.identity==="anon" &&
        <div className="card" style={{padding:"14px 16px",background:"var(--surface-2)",display:"flex",gap:12,alignItems:"center"}}>
          <span className="check on" style={{pointerEvents:"none"}}><span className="box"><Icon name="check"/></span></span>
          <span className="sm muted">ยืนยันว่าไม่ใช่บอท (CAPTCHA จำลองผ่านแล้ว)</span>
        </div>}
    </div>
  );
}

function Step4({ data }) {
  const typeLabel = {complaint:"ร้องเรียน",grievance:"ร้องทุกข์",tip:"แจ้งเบาะแส"}[data.type];
  return (
    <div className="grid" style={{gap:20}}>
      <div className="notice notice-info"><Icon name="info"/><div>โปรดตรวจทานข้อมูลก่อนยืนยัน เมื่อยื่นแล้วระบบจะออกรหัสติดตาม (Ticket) และส่งอีเมลยืนยันให้ท่าน</div></div>
      <dl className="kv">
        <dt>วิธีการยื่น</dt><dd>{data.identity==="google"?"ยืนยันตัวตน (Google)":"ไม่ประสงค์ออกนาม"}</dd>
        <dt>ประเภทเรื่อง</dt><dd>{typeLabel}</dd>
        <dt>สายงาน</dt><dd>{data.track?TRACKS[data.track].label+" · "+data.cat:"—"}</dd>
        <dt>หัวข้อเรื่อง</dt><dd>{data.subject||"—"}</dd>
        <dt>หน่วยงานเกี่ยวข้อง</dt><dd>{data.agency||"—"}</dd>
        <dt>รายละเอียด</dt><dd style={{fontWeight:400,color:"var(--ink-2)"}}>{data.detail||"—"}</dd>
        <dt>ไฟล์แนบ</dt><dd>{data.files.length?data.files.map(f=>f.n).join(", "):"ไม่มี"}</dd>
        <dt>ผู้ร้อง</dt><dd>{data.identity==="anon"?"ไม่ประสงค์ออกนาม":(data.name||"—")}</dd>
        <dt>ช่องทางติดต่อ</dt><dd>{data.email}{data.phone?" · "+data.phone:""}</dd>
      </dl>
    </div>
  );
}

function SubmitSuccess({ ticket, data, go }) {
  return (
    <div className="container fade-in" style={{maxWidth:620,padding:"60px 28px",textAlign:"center"}}>
      <div style={{width:84,height:84,borderRadius:"50%",background:"var(--ok-bg)",display:"grid",placeItems:"center",margin:"0 auto 22px"}}>
        <Icon name="checkCircle" style={{width:42,height:42,color:"var(--ok)"}}/>
      </div>
      <h1 style={{fontSize:27}}>ยื่นเรื่องเรียบร้อยแล้ว</h1>
      <p className="muted" style={{marginTop:10,marginBottom:24}}>ระบบได้รับเรื่องของท่านและส่งอีเมลยืนยันไปยัง <b style={{color:"var(--ink)"}}>{data.email}</b> แล้ว</p>
      <div className="card card-pad" style={{textAlign:"left",padding:24}}>
        <div className="muted sm">รหัสติดตามเรื่อง (Ticket Code)</div>
        <div className="between" style={{marginTop:6}}>
          <div className="code" style={{fontSize:26}}>{ticket}</div>
          <span className="badge badge-info"><span className="dot"></span>รับเรื่อง</span>
        </div>
        <hr className="hr" style={{margin:"18px 0"}}/>
        <div className="notice notice-warn"><Icon name="info"/><div>โปรดบันทึกรหัสนี้ไว้ ท่านสามารถใช้รหัสร่วมกับอีเมลเพื่อติดตามสถานะได้ตลอดเวลา โดยจะเห็นเฉพาะสถานะย่อเพื่อความปลอดภัย</div></div>
      </div>
      <div className="row" style={{justifyContent:"center",marginTop:24}}>
        <button className="btn btn-outline" onClick={()=>go("home")}>กลับหน้าแรก</button>
        <button className="btn btn-primary" onClick={()=>go("track",{ticket})}><Icon name="search" style={{width:16,height:16}}/> ติดตามสถานะ</button>
      </div>
    </div>
  );
}

/* ---------------- ติดตามสถานะ ---------------- */
function TrackStatus({ go, preset }) {
  const [code, setCode] = useState(preset?.ticket || "");
  const [email, setEmail] = useState("");
  const [result, setResult] = useState(null);
  const [searched, setSearched] = useState(false);
  const [loading, setLoading] = useState(false);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    if (preset?.ticket) doSearch(preset.ticket);
  }, []);

  const doSearch = async (overrideCode) => {
    const q = (overrideCode || code).trim();
    if (!q) return;
    setLoading(true); setNotFound(false); setSearched(false);
    try {
      const data = await api.trackCase(q);
      setResult(data); setSearched(true);
    } catch {
      setNotFound(true); setSearched(true); setResult(null);
    } finally {
      setLoading(false);
    }
  };
  const pubSteps = ["รับเรื่อง","คัดกรอง","แปลงเป็นสำนวน","ดำเนินการ","แจ้งผล"];
  const stepOf = (s)=>({received:0,screening:1,rejected:1,case:2,assigned:3,investigating:3,reporting:3,closed:4}[s]??0);

  return (
    <div className="container fade-in" style={{maxWidth:760,padding:"40px 28px 80px"}}>
      <button className="btn btn-ghost btn-sm" onClick={()=>go("home")} style={{marginBottom:14}}><Icon name="chevL" style={{width:16,height:16}}/> กลับหน้าแรก</button>
      <h1 style={{fontSize:26}}>ติดตามสถานะเรื่อง</h1>
      <p className="muted" style={{marginTop:6,marginBottom:24}}>กรอกรหัสติดตามและอีเมลที่ใช้ยื่นเรื่องเพื่อดูสถานะ (เพื่อความปลอดภัย ระบบจะแสดงเฉพาะสถานะย่อ ไม่เปิดเผยข้อมูลส่วนบุคคล)</p>

      <div className="card card-pad">
        <div className="grid" style={{gridTemplateColumns:"1.2fr 1fr auto",gap:12,alignItems:"flex-end"}}>
          <div className="field"><label>รหัสติดตาม (Ticket)</label>
            <input className="input" placeholder="เช่น CMP-2568-0142" value={code} onChange={e=>setCode(e.target.value)} /></div>
          <div className="field"><label>อีเมลที่ใช้ยื่นเรื่อง</label>
            <input className="input" placeholder="you@email.com" value={email} onChange={e=>setEmail(e.target.value)} /></div>
          <button className="btn btn-primary" onClick={()=>doSearch()} disabled={loading} style={{height:43}}><Icon name="search" style={{width:16,height:16}}/> ค้นหา</button>
        </div>
      </div>

      {loading && <LoadingSpinner/>}
      {searched && result &&
        <div className="card card-pad fade-in" style={{marginTop:20}}>
          <div className="between" style={{flexWrap:"wrap",gap:12}}>
            <div>
              <div className="muted sm">รหัสติดตาม</div>
              <div className="code" style={{fontSize:20}}>{result.id}</div>
            </div>
            <StatusBadge s={result.status}/>
          </div>
          <hr className="hr" style={{margin:"18px 0"}}/>
          <div className="stepper" style={{marginBottom:8}}>
            {pubSteps.map((s,i)=>{
              const cur = stepOf(result.status);
              return (<React.Fragment key={i}>
                <div className={"step "+(i===cur?"active":i<cur?"done":"")}>
                  <div className="num">{i<cur?<Icon name="check" style={{width:14,height:14}}/>:i+1}</div>
                  <div className="stt">{s}</div>
                </div>
                {i<pubSteps.length-1 && <div className={"bar "+(i<cur?"done":"")}></div>}
              </React.Fragment>);
            })}
          </div>
          <div className="notice notice-info" style={{marginTop:18}}><Icon name="info"/><div>เรื่องของท่านอยู่ระหว่างการดำเนินการของกลุ่มนิติการ หากต้องการข้อมูลเพิ่มเติม เจ้าหน้าที่จะติดต่อกลับผ่านช่องทางที่ท่านให้ไว้</div></div>
          <div className="muted tiny" style={{marginTop:14}}>อัปเดตล่าสุด: {thDate(result.received)} · ช่องทางยื่น: {result.channel}</div>
        </div>}
      {searched && notFound &&
        <div className="notice notice-warn" style={{marginTop:20}}><Icon name="alert"/><div>ไม่พบเรื่องที่ตรงกับรหัสที่ระบุ โปรดตรวจสอบอีกครั้ง</div></div>}
    </div>
  );
}

Object.assign(window, { ThemeToggle, PubHeader, PubFooter, PublicHome, ComplaintForm, TrackStatus });
