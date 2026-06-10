/* ============================================================
   data.jsx — ไอคอน, constants, helpers, API client
   ============================================================ */
const { useState, useEffect, useRef, useMemo, useCallback,
        createContext, useContext } = React;

/* ---------------- Icons (line / stroke) ---------------- */
const P = {
  home: "M3 11l9-8 9 8M5 9.5V21h14V9.5",
  file: "M14 3v5h5M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z",
  filePlus: "M14 3v5h5M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM12 12v6M9 15h6",
  inbox: "M22 12h-6l-2 3h-4l-2-3H2M5.5 5.5L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z",
  search: "M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3",
  filter: "M3 5h18l-7 8v6l-4 2v-8z",
  user: "M20 21a8 8 0 1 0-16 0M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z",
  users: "M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13A4 4 0 0 1 16 11",
  shield: "M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z",
  shieldCheck: "M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4",
  gavel: "M14 13l-7.5 7.5a2.12 2.12 0 0 1-3-3L11 10M14.5 5.5l4 4M8.5 8.5l7-7 4 4-7 7zM3 21h9",
  scale: "M12 3v18M7 7l-4 7h8zM17 7l4 7h-8zM4 21h16M8 7h8",
  clock: "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2",
  check: "M20 6L9 17l-5-5",
  checkCircle: "M22 11.5V12a10 10 0 1 1-5.9-9.1M22 4L12 14.1l-3-3",
  x: "M18 6L6 18M6 6l12 12",
  chevR: "M9 18l6-6-6-6",
  chevL: "M15 18l-6-6 6-6",
  chevD: "M6 9l6 6 6-6",
  arrowR: "M5 12h14M13 6l6 6-6 6",
  plus: "M12 5v14M5 12h14",
  bell: "M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0",
  chart: "M3 3v18h18M7 16V11M12 16V7M17 16v-3",
  pie: "M21.2 15.9A10 10 0 1 1 8.1 2.8M22 12A10 10 0 0 0 12 2v10z",
  layers: "M12 2l9 5-9 5-9-5 9-5zM3 12l9 5 9-5M3 17l9 5 9-5",
  settings: "M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 0 1-4 0v-.1A1.6 1.6 0 0 0 7 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H1a2 2 0 0 1 0-4h.1A1.6 1.6 0 0 0 2.6 7a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H7a1.6 1.6 0 0 0 1-1.5V1a2 2 0 0 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V7a1.6 1.6 0 0 0 1.5 1H23a2 2 0 0 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z",
  sun: "M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4",
  moon: "M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z",
  monitor: "M3 4h18v12H3zM8 20h8M12 16v4",
  logout: "M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9",
  lock: "M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4",
  mail: "M4 4h16v16H4zM22 6l-10 7L2 6",
  phone: "M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.7a2 2 0 0 1-.5 2.1L8 9.6a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.1-.5c.9.3 1.8.5 2.7.6a2 2 0 0 1 1.7 2z",
  pin: "M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z",
  calendar: "M3 4h18v18H3zM3 9h18M8 2v4M16 2v4",
  paperclip: "M21.4 11.05l-9.2 9.2a5 5 0 0 1-7-7l9.2-9.2a3 3 0 0 1 4.3 4.3l-9.3 9.2a1 1 0 0 1-1.4-1.4l8.5-8.5",
  send: "M22 2L11 13M22 2l-7 20-4-9-9-4z",
  google: "M21.8 12.2c0-.7-.1-1.4-.2-2H12v3.8h5.5a4.7 4.7 0 0 1-2 3.1v2.6h3.3c1.9-1.8 3-4.4 3-7.5z|M12 22c2.7 0 5-.9 6.6-2.4l-3.3-2.5a6 6 0 0 1-9-3.1H3v2.6A10 10 0 0 0 12 22z|M5.3 14a6 6 0 0 1 0-3.8V7.6H3a10 10 0 0 0 0 9z|M12 6a5.4 5.4 0 0 1 3.8 1.5l2.9-2.9A10 10 0 0 0 3 7.6l3.3 2.6A6 6 0 0 1 12 6z",
  edit: "M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z",
  eye: "M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z",
  download: "M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3",
  alert: "M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01",
  info: "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 16v-4M12 8h.01",
  flag: "M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7",
  forward: "M15 17l5-5-5-5M4 18v-2a4 4 0 0 1 4-4h12",
  briefcase: "M20 7h-16a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2",
  trend: "M23 6l-9.5 9.5-5-5L1 18M17 6h6v6",
  menu: "M3 12h18M3 6h18M3 18h18",
  hash: "M4 9h16M4 15h16M10 3L8 21M16 3l-2 18",
  dot: "M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0-6 0",
  building: "M3 21h18M5 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M19 21V11a1 1 0 0 0-1-1h-3M9 7h2M9 11h2M9 15h2",
  stamp: "M5 22h14M6 18h12v-2a3 3 0 0 0-3-3h-1l1-5a3 3 0 0 0-3-3 3 3 0 0 0-3 3l1 5H9a3 3 0 0 0-3 3z",
};

function Icon({ name, style, className }) {
  const d = P[name] || P.dot;
  const paths = d.split("|");
  const isGoogle = name === "google";
  return (
    <svg className={className} style={style} viewBox="0 0 24 24" fill="none"
      stroke={isGoogle ? "none" : "currentColor"} strokeWidth="1.9"
      strokeLinecap="round" strokeLinejoin="round" width="20" height="20">
      {paths.map((p, i) => (
        <path key={i} d={p} fill={isGoogle ? ["#4285F4","#34A853","#FBBC05","#EA4335"][i] : "none"} />
      ))}
    </svg>
  );
}

/* ---------------- Master data (constants) ---------------- */
const STATUS = {
  received:      { label: "รับเรื่อง",          cls: "badge-info",   step: 0 },
  screening:     { label: "คัดกรอง",            cls: "badge-warn",   step: 1 },
  rejected:      { label: "ไม่รับเรื่อง",        cls: "badge-danger", step: 1 },
  case:          { label: "แปลงเป็นสำนวน",      cls: "badge-maroon", step: 2 },
  assigned:      { label: "มอบหมายแล้ว",        cls: "badge-maroon", step: 3 },
  investigating: { label: "อยู่ระหว่างสอบสวน", cls: "badge-warn",   step: 4 },
  reporting:     { label: "สรุป/รายงานผล",      cls: "badge-info",   step: 5 },
  closed:        { label: "เสร็จสิ้น",           cls: "badge-ok",     step: 6 },
};
const STEPS    = ["รับเรื่อง","คัดกรอง","แปลงสำนวน","มอบหมาย","สอบสวน","รายงานผล","เสร็จสิ้น"];
const TRACKS   = {
  discipline: { label: "ด้านวินัย",  group: "กลุ่มงานวินัย",
    cats: ["งานร้องเรียน","งานวินัย","งานอุทธรณ์","งานร้องทุกข์"] },
  legal:      { label: "ด้านกฎหมาย", group: "กลุ่มงานกฎหมายและระเบียบ",
    cats: ["ระเบียบ/กฎหมาย/คำสั่ง","นิติกรรมสัญญา","คดีปกครอง/แพ่ง/อาญา","ความรับผิดทางละเมิด"] },
};
const CHANNELS = ["เว็บไซต์ (ยืนยันตัวตน)","เว็บไซต์ (ไม่ประสงค์ออกนาม)","หนังสือราชการ","ศูนย์ดำรงธรรม","โทรศัพท์ / สายด่วน","ไปรษณีย์อิเล็กทรอนิกส์"];
const CLASS    = { public:{l:"ทั่วไป",c:"badge"}, internal:{l:"ภายใน",c:"badge-info"}, restricted:{l:"จำกัด",c:"badge-warn"}, secret:{l:"ลับ",c:"badge-danger"} };

/* ---------------- API client ---------------- */
const _base = (window.__APP_BASE__ || '').replace(/\/$/, '');
async function apiFetch(path, options = {}) {
  const url = _base + path;
  const res = await fetch(url, {
    headers: { 'Content-Type': 'application/json', ...(options.headers||{}) },
    credentials: 'same-origin',
    ...options,
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({ error: res.statusText }));
    throw new Error(err.error || res.statusText);
  }
  return res.json();
}

const api = {
  getMe:       ()         => apiFetch('/api/auth.php'),
  login:       (u, p)     => apiFetch('/api/auth.php', { method:'POST', body: JSON.stringify({username:u,password:p}) }),
  logout:      ()         => apiFetch('/api/auth.php', { method:'DELETE' }),
  getCases:    (params={})=> apiFetch('/api/cases.php?' + new URLSearchParams(params).toString()),
  getCase:     (id)       => apiFetch('/api/cases.php?id=' + encodeURIComponent(id)),
  createCase:  (data)     => apiFetch('/api/cases.php', { method:'POST', body: JSON.stringify(data) }),
  updateCase:  (id, data) => apiFetch('/api/cases.php?id=' + encodeURIComponent(id), { method:'PATCH', body: JSON.stringify(data) }),
  getOfficers: ()         => apiFetch('/api/officers.php'),
  trackCase:   (id)       => apiFetch('/api/cases.php?id=' + encodeURIComponent(id)),
};

// ตรวจสอบ base path ว่าถูกต้องหรือไม่ (debug เฉพาะ dev)
if (window.__APP_BASE__ === undefined) console.warn('[API] __APP_BASE__ ไม่ถูก inject — ตรวจสอบ index.php');

/* ---------------- Helpers ---------------- */
function officerById(officers, id) { return (officers||[]).find(o => o.id === id); }

function thDate(iso) {
  if (!iso || iso === "—") return "—";
  const m = ["ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
  const [y, mo, d] = iso.split("-").map(Number);
  return `${d} ${m[mo-1]} ${y + 543}`;
}

function StatusBadge({ s }) {
  const info = STATUS[s] || STATUS.received;
  return <span className={"badge " + info.cls}><span className="dot"></span>{info.label}</span>;
}
function SLAText({ s, label }) {
  const map = { g:"ตามกำหนด", a:"ใกล้ครบกำหนด", r:"เกินกำหนด" };
  return <span className={"sla sla-" + s}>{label || map[s]}</span>;
}
function PriBadge({ p }) {
  if (p === "เร่งด่วน") return <span className="badge badge-danger"><span className="dot"></span>เร่งด่วน</span>;
  if (p === "ลับ")      return <span className="badge badge-warn"><span className="dot"></span>ลับ</span>;
  return <span className="badge">ปกติ</span>;
}

function LoadingSpinner() {
  return (
    <div style={{display:"flex",alignItems:"center",justifyContent:"center",padding:48,gap:12,color:"var(--ink-3)"}}>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="22" height="22"
           style={{animation:"spin 1s linear infinite",flexShrink:0}}>
        <path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/>
      </svg>
      <span>กำลังโหลด…</span>
      <style>{`@keyframes spin{to{transform:rotate(360deg)}}`}</style>
    </div>
  );
}

Object.assign(window, {
  React, useState, useEffect, useRef, useMemo, useCallback, createContext, useContext,
  Icon, P, STATUS, STEPS, TRACKS, CHANNELS, CLASS,
  api, apiFetch, officerById, thDate, StatusBadge, SLAText, PriBadge, LoadingSpinner,
});
