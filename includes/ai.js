// src/services/aiService.js
const AI_URL = import.meta.env.VITE_AI_URL?.trim() || "https://orias171-doc-ai-api.hf.space/process_pdf";
// http://127.0.0.1:8000/process_pdf
async function summarizePDF(file, abortSignal) {
  const fd = new FormData();
  fd.append("file", file); // Flask đọc request.files["file"]
  const res = await fetch(AI_URL, { method: "POST", body: fd, signal: abortSignal });
  if (!res.ok) {
    let msg = "";
    try { msg = await res.text(); } catch {}
    throw new Error(`AI HTTP ${res.status}: ${msg || "Unknown error"}`);
  }
  const data = await res.json();
  if (data.status !== "success") throw new Error(data.message || "AI xử lý thất bại");
  return data; // { status, summary, category, top_candidates }
}
export default { summarizePDF };