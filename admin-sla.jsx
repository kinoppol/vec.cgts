/* ============================================================
   admin-sla.jsx — ตั้งค่า SLA (Service Level Agreement)
   เข้าถึงได้โดย: admin, dir_legal
   ============================================================ */

function SlaSettingsPage({ currentUser }) {
  const [settings, setSettings] = useState([]);
  const [loading,  setLoading]  = useState(true);
  const [drafts,   setDrafts]   = useState({});
  const [saving,   setSaving]   = useState({});
  const [flash,    setFlash]    = useState('');
  const [errMsg,   setErrMsg]   = useState('');

  const canEdit = currentUser.role === 'admin' || currentUser.role === 'dir_legal';

  useEffect(() => {
    api.getSlaSettings()
      .then(rows => {
        setSettings(rows);
        const d = {};
        rows.forEach(r => { d[slaKey(r)] = { days: String(r.days), note: r.note || '' }; });
        setDrafts(d);
      })
      .catch(e => setErrMsg(e.message))
      .finally(() => setLoading(false));
  }, []);

  function slaKey(r) { return r.track + '/' + r.cat; }

  function setField(track, cat, field, val) {
    setDrafts(d => ({ ...d, [track + '/' + cat]: { ...(d[track + '/' + cat] || {}), [field]: val } }));
  }

  function isDirty(r) {
    const d = drafts[slaKey(r)];
    if (!d) return false;
    return parseInt(d.days) !== parseInt(r.days) || d.note !== (r.note || '');
  }

  async function save(r) {
    const k    = slaKey(r);
    const d    = drafts[k];
    const days = parseInt(d?.days);
    if (!days || days < 1) { setErrMsg('จำนวนวันต้องมากกว่า 0'); return; }
    setSaving(s => ({ ...s, [k]: true }));
    setErrMsg('');
    try {
      const updated = await api.saveSlaSettings({ track: r.track, cat: r.cat, days, note: d.note });
      setSettings(ss => ss.map(s => s.track === r.track && s.cat === r.cat ? { ...s, ...updated } : s));
      showFlash('บันทึก "' + r.cat + '" เรียบร้อย');
    } catch(e) { setErrMsg(e.message); }
    setSaving(s => ({ ...s, [k]: false }));
  }

  function showFlash(msg) {
    setFlash(msg);
    setTimeout(() => setFlash(''), 3500);
  }

  function fmtUpdated(ts) {
    if (!ts) return '—';
    const d = new Date(ts);
    return d.toLocaleDateString('th-TH', { day:'numeric', month:'short', year:'numeric' })
         + ' ' + d.toLocaleTimeString('th-TH', { hour:'2-digit', minute:'2-digit' });
  }

  const trackList = ['discipline', 'legal'];

  return (
    <div className="fade-in">
      <PageHead title="ตั้งค่า SLA" sub="กำหนดระยะเวลาดำเนินการสูงสุด (วัน) สำหรับแต่ละสายงานและหมวดงาน"/>

      {errMsg && (
        <div className="notice notice-err" style={{marginBottom:16}}>
          <Icon name="alert"/><div>{errMsg}</div>
        </div>
      )}
      {flash && (
        <div className="notice notice-ok" style={{marginBottom:16}}>
          <Icon name="checkCircle"/><div>{flash}</div>
        </div>
      )}

      <div className="notice notice-info" style={{marginBottom:20}}>
        <Icon name="info"/>
        <div>
          <b>SLA (Service Level Agreement)</b> คือจำนวนวันสูงสุดที่แต่ละประเภทงานควรแล้วเสร็จ
          นับจากวันรับเรื่อง ระบบจะใช้ค่านี้คำนวณ <b>วันครบกำหนด</b> และแสดงสัญญาณไฟจราจร
          <span style={{marginLeft:10,display:"inline-flex",gap:6,verticalAlign:"middle"}}>
            <span className="sla sla-g">● ตามกำหนด</span>
            <span className="sla sla-a">● ใกล้ครบ</span>
            <span className="sla sla-r">● เกินกำหนด</span>
          </span>
        </div>
      </div>

      {loading ? <LoadingSpinner/> : trackList.map(track => {
        const rows = settings.filter(s => s.track === track);
        if (!rows.length) return null;
        return (
          <div key={track} className="card" style={{marginBottom:20}}>
            {/* Track header */}
            <div style={{padding:"14px 20px",borderBottom:"1px solid var(--line)",display:"flex",alignItems:"center",gap:12}}>
              <div style={{width:36,height:36,borderRadius:8,background:"var(--maroon-50,#f9f0f1)",
                           color:"var(--maroon)",display:"grid",placeItems:"center",flexShrink:0}}>
                <Icon name={track==='discipline'?'gavel':'scale'} style={{width:18,height:18}}/>
              </div>
              <div>
                <div style={{fontWeight:700,fontSize:16}}>{TRACKS[track]?.label}</div>
                <div className="faint tiny">{TRACKS[track]?.group}</div>
              </div>
              <span className="badge" style={{marginLeft:"auto"}}>{rows.length} หมวด</span>
            </div>

            {/* Table */}
            <div className="table-wrap">
              <table className="tbl">
                <thead>
                  <tr>
                    <th>หมวดงาน</th>
                    <th style={{width:160}}>ระยะเวลา SLA</th>
                    <th>หมายเหตุ / อ้างอิงระเบียบ</th>
                    <th style={{width:170}}>แก้ไขล่าสุด</th>
                    {canEdit && <th style={{width:90,textAlign:"center"}}></th>}
                  </tr>
                </thead>
                <tbody>
                  {rows.map(row => {
                    const k     = slaKey(row);
                    const d     = drafts[k] || { days: String(row.days), note: row.note || '' };
                    const dirty = isDirty(row);
                    const busy  = !!saving[k];

                    return (
                      <tr key={row.id}>
                        <td style={{fontWeight:600}}>{row.cat}</td>

                        <td>
                          {canEdit ? (
                            <div className="vcenter" style={{gap:7}}>
                              <input type="number" className="input" min="1" max="3650"
                                style={{width:76,textAlign:"center",padding:"5px 8px",fontSize:14}}
                                value={d.days}
                                onChange={e => setField(row.track, row.cat, 'days', e.target.value)}/>
                              <span className="faint sm">วัน</span>
                            </div>
                          ) : (
                            <span className="tnum" style={{fontWeight:600}}>{row.days}
                              <span className="faint sm" style={{marginLeft:4}}>วัน</span>
                            </span>
                          )}
                        </td>

                        <td>
                          {canEdit ? (
                            <input type="text" className="input"
                              style={{padding:"5px 10px",fontSize:13,width:"100%"}}
                              placeholder="เช่น ตาม พ.ร.บ. / ระเบียบ สอศ."
                              value={d.note}
                              onChange={e => setField(row.track, row.cat, 'note', e.target.value)}/>
                          ) : (
                            <span className="sm muted">{row.note || '—'}</span>
                          )}
                        </td>

                        <td>
                          {row.updated_by_name ? (
                            <div>
                              <div className="sm" style={{fontWeight:500}}>{row.updated_by_name}</div>
                              <div className="faint tiny">{fmtUpdated(row.updated_at)}</div>
                            </div>
                          ) : (
                            <span className="faint tiny">ค่าเริ่มต้นระบบ</span>
                          )}
                        </td>

                        {canEdit && (
                          <td style={{textAlign:"center"}}>
                            <button className="btn btn-sm"
                              style={{
                                background: dirty && !busy ? "var(--maroon)" : "var(--line)",
                                color:      dirty && !busy ? "#fff" : "var(--ink-3)",
                                transition: "all .18s",
                                minWidth:   68,
                              }}
                              disabled={!dirty || busy}
                              onClick={() => save(row)}>
                              {busy ? '…' : dirty ? 'บันทึก' : 'บันทึก'}
                            </button>
                          </td>
                        )}
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        );
      })}

      {!loading && !canEdit && (
        <div className="notice notice-warn">
          <Icon name="lock"/>
          <div>คุณมีสิทธิ์ดูข้อมูลเท่านั้น การแก้ไขต้องใช้สิทธิ์ <b>ผอ.กลุ่มนิติการ</b> หรือ <b>ผู้ดูแลระบบ</b></div>
        </div>
      )}
    </div>
  );
}

Object.assign(window, { SlaSettingsPage });
