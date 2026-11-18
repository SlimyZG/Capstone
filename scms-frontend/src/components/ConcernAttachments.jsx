import { useState, useEffect, useCallback } from 'react';
import {
  Paperclip,
  Image as ImageIcon,
  Film,
  FileText,
  Download,
  Maximize2,
  X,
  RefreshCw,
  AlertCircle,
  ExternalLink,
} from 'lucide-react';
import api from '../api';

const IMAGE_EXT_REGEX = /\.(jpe?g|png|gif|webp)$/i;
const VIDEO_EXT_REGEX = /\.(mp4|webm|mov|avi|mkv)$/i;

/**
 * Extract a clean display filename from a stored attachment entry without exposing internal paths.
 */
function getDisplayFileName(att, index) {
  if (typeof att === 'object' && att !== null) {
    if (att.file_name || att.name || att.original_name) {
      return att.file_name || att.name || att.original_name;
    }
  }
  const raw = typeof att === 'string' ? att : (att?.file_path || att?.path || '');
  if (!raw) return `Attachment ${index + 1}`;
  const base = raw.split('/').pop().split('\\').pop() || '';
  // Strip generated prefix "att_<concernId>_<index>_<uniqid>_" if present
  const cleaned = base.replace(/^att_\d+_\d+_[a-f0-9]+_/i, '');
  if (cleaned && cleaned.includes('.')) {
    return cleaned;
  }
  return `Attachment ${index + 1}`;
}

function inferMediaKind(att, mimeType = '') {
  const lowerMime = (mimeType || '').toLowerCase();
  if (lowerMime.startsWith('image/')) return 'image';
  if (lowerMime.startsWith('video/')) return 'video';

  const raw = typeof att === 'string' ? att : (att?.file_path || att?.path || att?.name || '');
  if (IMAGE_EXT_REGEX.test(raw)) return 'image';
  if (VIDEO_EXT_REGEX.test(raw)) return 'video';
  return 'document';
}

function AttachmentItem({ concernId, att, index, compact = false, onOpenLightbox }) {
  const [status, setStatus] = useState('loading'); // 'loading' | 'ready' | 'error'
  const [blobUrl, setBlobUrl] = useState(null);
  const [mimeType, setMimeType] = useState('');
  const [errorMsg, setErrorMsg] = useState('');

  const fileName = getDisplayFileName(att, index);

  const loadAttachment = useCallback(async () => {
    setStatus('loading');
    setErrorMsg('');
    try {
      const response = await api.get(`/concerns/${concernId}/attachments/${index}`, {
        responseType: 'blob',
      });
      const contentType = response.headers?.['content-type'] || response.data?.type || 'application/octet-stream';
      const blob = new Blob([response.data], { type: contentType });
      const objectUrl = URL.createObjectURL(blob);
      setBlobUrl((prev) => {
        if (prev) URL.revokeObjectURL(prev);
        return objectUrl;
      });
      setMimeType(contentType);
      setStatus('ready');
    } catch (err) {
      let msg = 'Unable to load attachment.';
      if (err.response?.status === 403) {
        msg = 'You are not authorized to view this attachment.';
      } else if (err.response?.status === 404) {
        msg = 'Attachment file could not be found.';
      }
      setErrorMsg(msg);
      setStatus('error');
    }
  }, [concernId, index]);

  useEffect(() => {
    let active = true;
    if (concernId !== undefined && concernId !== null) {
      loadAttachment().catch(() => {
        if (active) setStatus('error');
      });
    }
    return () => {
      active = false;
      setBlobUrl((prev) => {
        if (prev) URL.revokeObjectURL(prev);
        return null;
      });
    };
  }, [concernId, loadAttachment]);

  const mediaKind = inferMediaKind(att, mimeType);

  if (status === 'loading') {
    return (
      <div
        className={`flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 ${
          compact ? 'px-3 py-2 text-xs' : 'p-3.5 text-xs'
        }`}
        data-testid={`attachment-loading-${index}`}
      >
        <RefreshCw size={14} className="animate-spin text-blue-600 shrink-0" />
        <span className="truncate font-medium">Loading {fileName}…</span>
      </div>
    );
  }

  if (status === 'error' || !blobUrl) {
    return (
      <div
        className={`flex items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50/70 text-red-700 ${
          compact ? 'px-3 py-2 text-xs' : 'p-3.5 text-xs'
        }`}
        data-testid={`attachment-error-${index}`}
      >
        <div className="flex items-center gap-2 min-w-0">
          <AlertCircle size={15} className="shrink-0 text-red-600" />
          <div className="min-w-0">
            <p className="font-semibold truncate">{fileName}</p>
            <p className="text-[11px] text-red-600/90">{errorMsg || 'Attachment failed to load.'}</p>
          </div>
        </div>
        <button
          type="button"
          onClick={loadAttachment}
          className="px-2.5 py-1 rounded-lg bg-white border border-red-200 text-[11px] font-semibold text-red-700 hover:bg-red-100 transition-colors shrink-0"
        >
          Retry
        </button>
      </div>
    );
  }

  if (mediaKind === 'image') {
    return (
      <div
        className="group relative rounded-xl border border-slate-200 bg-white overflow-hidden shadow-2xs hover:border-blue-300 transition-all"
        data-testid={`attachment-image-${index}`}
      >
        <div
          className={`relative bg-slate-100 overflow-hidden cursor-pointer flex items-center justify-center ${
            compact ? 'h-24 w-36' : 'h-44 w-full sm:w-60'
          }`}
          onClick={() => onOpenLightbox && onOpenLightbox({ url: blobUrl, fileName, kind: 'image' })}
        >
          <img
            src={blobUrl}
            alt={fileName}
            className="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-200"
            onError={() => {
              setErrorMsg('Image preview could not be rendered.');
              setStatus('error');
            }}
          />
          <div className="absolute inset-0 bg-slate-900/0 group-hover:bg-slate-900/30 transition-colors flex items-center justify-center">
            <span className="opacity-0 group-hover:opacity-100 transition-opacity bg-white/95 text-slate-900 px-2.5 py-1 rounded-lg text-[11px] font-semibold shadow-sm inline-flex items-center gap-1">
              <Maximize2 size={12} /> View Full Image
            </span>
          </div>
        </div>
        <div className="px-3 py-2 bg-white border-t border-slate-100 flex items-center justify-between gap-2">
          <div className="flex items-center gap-1.5 min-w-0">
            <ImageIcon size={13} className="text-blue-600 shrink-0" />
            <span className="text-xs font-medium text-slate-700 truncate" title={fileName}>
              {fileName}
            </span>
          </div>
          <a
            href={blobUrl}
            download={fileName}
            className="p-1 text-slate-500 hover:text-blue-600 rounded hover:bg-slate-100 transition-colors shrink-0"
            title={`Download ${fileName}`}
          >
            <Download size={13} />
          </a>
        </div>
      </div>
    );
  }

  if (mediaKind === 'video') {
    return (
      <div
        className={`rounded-xl border border-slate-200 bg-white overflow-hidden shadow-2xs ${
          compact ? 'w-64' : 'w-full sm:max-w-md'
        }`}
        data-testid={`attachment-video-${index}`}
      >
        <div className="bg-slate-950 flex items-center justify-center">
          <video
            src={blobUrl}
            controls
            preload="metadata"
            playsInline
            className={compact ? 'max-h-36 w-full' : 'max-h-64 w-full'}
            onError={() => {
              setErrorMsg('Video stream could not be played.');
              setStatus('error');
            }}
          >
            Your browser does not support HTML5 video playback.
          </video>
        </div>
        <div className="px-3 py-2 bg-white border-t border-slate-100 flex items-center justify-between gap-2">
          <div className="flex items-center gap-1.5 min-w-0">
            <Film size={13} className="text-indigo-600 shrink-0" />
            <span className="text-xs font-medium text-slate-700 truncate" title={fileName}>
              {fileName}
            </span>
          </div>
          <a
            href={blobUrl}
            download={fileName}
            className="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold text-blue-600 hover:bg-blue-50 rounded transition-colors shrink-0"
          >
            <Download size={12} /> Download
          </a>
        </div>
      </div>
    );
  }

  return (
    <div
      className="inline-flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 shadow-2xs transition-colors"
      data-testid={`attachment-document-${index}`}
    >
      <div className="flex items-center gap-2 min-w-0">
        <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
          <FileText size={14} />
        </div>
        <span className="text-xs font-semibold text-slate-800 truncate max-w-[180px]" title={fileName}>
          {fileName}
        </span>
      </div>
      <div className="flex items-center gap-1 shrink-0">
        <a
          href={blobUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="p-1.5 text-slate-600 hover:text-blue-600 rounded-lg hover:bg-blue-50 transition-colors"
          title="Open attachment"
        >
          <ExternalLink size={13} />
        </a>
        <a
          href={blobUrl}
          download={fileName}
          className="p-1.5 text-slate-600 hover:text-blue-600 rounded-lg hover:bg-blue-50 transition-colors"
          title="Download attachment"
        >
          <Download size={13} />
        </a>
      </div>
    </div>
  );
}

const ConcernAttachments = ({ concernId, attachments, compact = false, titleClassName = '' }) => {
  const [lightbox, setLightbox] = useState(null);

  if (!Array.isArray(attachments) || attachments.length === 0) {
    return null;
  }

  return (
    <div className="space-y-2.5" data-testid="concern-attachments-section">
      <div className="flex items-center gap-1.5">
        <Paperclip size={13} className="text-slate-400" />
        <p className={titleClassName || 'text-xs font-bold text-slate-500 uppercase tracking-wider'}>
          Attachments ({attachments.length})
        </p>
      </div>

      <div className="flex flex-wrap gap-3 items-start">
        {attachments.map((att, idx) => (
          <AttachmentItem
            key={`${concernId}-att-${idx}`}
            concernId={concernId}
            att={att}
            index={idx}
            compact={compact}
            onOpenLightbox={(item) => setLightbox(item)}
          />
        ))}
      </div>

      {lightbox && (
        <div
          className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4"
          onClick={() => setLightbox(null)}
        >
          <div
            className="relative max-w-4xl w-full bg-white rounded-2xl overflow-hidden shadow-2xl border border-slate-200"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="px-4 py-3 bg-slate-900 text-white flex items-center justify-between gap-3">
              <span className="text-xs font-semibold truncate">{lightbox.fileName}</span>
              <div className="flex items-center gap-2">
                <a
                  href={lightbox.url}
                  download={lightbox.fileName}
                  className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-semibold text-white transition-colors"
                >
                  <Download size={13} /> Download
                </a>
                <button
                  type="button"
                  onClick={() => setLightbox(null)}
                  className="p-1 rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition-colors"
                  aria-label="Close preview"
                >
                  <X size={18} />
                </button>
              </div>
            </div>
            <div className="bg-slate-950 flex items-center justify-center max-h-[80vh] overflow-auto p-2">
              <img
                src={lightbox.url}
                alt={lightbox.fileName}
                className="max-h-[76vh] w-auto object-contain rounded"
              />
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ConcernAttachments;
