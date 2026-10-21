import { useToast } from '@/hooks/useToast';
import axios from 'axios';
import { Mic, Phone, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { callSessionSdpFrom, isIncomingActionableCall } from './helpers';
import { type Conversation, type InboxCallRecord } from './types';

export function IncomingCallScreen({
  call,
  conversation,
  callingReady,
  onCallUpdated,
  onClose,
}: {
  call: InboxCallRecord;
  conversation?: Conversation | null;
  callingReady: boolean;
  onCallUpdated: (call: InboxCallRecord) => void;
  onClose: () => void;
}) {
  const { addToast } = useToast();
  const [busy, setBusy] = useState<'answer' | 'reject' | 'end' | null>(null);
  const [localStatus, setLocalStatus] = useState(call.status);
  const peerRef = useRef<RTCPeerConnection | null>(null);
  const localStreamRef = useRef<MediaStream | null>(null);
  const remoteAudioRef = useRef<HTMLAudioElement | null>(null);

  useEffect(() => {
    setLocalStatus(call.status);
  }, [call.status]);

  const stopLocalCall = () => {
    peerRef.current?.close();
    peerRef.current = null;
    localStreamRef.current?.getTracks().forEach((track) => track.stop());
    localStreamRef.current = null;
    if (remoteAudioRef.current) {
      remoteAudioRef.current.pause();
      remoteAudioRef.current.srcObject = null;
      remoteAudioRef.current = null;
    }
  };

  useEffect(() => stopLocalCall, []);

  const createPeer = async () => {
    stopLocalCall();
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    const peer = new RTCPeerConnection();
    stream.getTracks().forEach((track) => peer.addTrack(track, stream));
    peer.ontrack = (event) => {
      const [remoteStream] = event.streams;
      if (!remoteStream) return;
      const audio = remoteAudioRef.current || new Audio();
      audio.autoplay = true;
      audio.srcObject = remoteStream;
      remoteAudioRef.current = audio;
      void audio.play().catch(() => undefined);
    };
    peerRef.current = peer;
    localStreamRef.current = stream;

    return peer;
  };

  const acceptCall = async () => {
    const offerSdp = callSessionSdpFrom(call);
    if (!offerSdp) {
      addToast({
        title: 'Cannot answer yet',
        description: 'The call is still connecting. Please wait a moment and try again.',
        variant: 'warning',
      });
      return;
    }

    setBusy('answer');
    try {
      const peer = await createPeer();
      await peer.setRemoteDescription({ type: 'offer', sdp: offerSdp });
      const answer = await peer.createAnswer();
      await peer.setLocalDescription(answer);
      const response = await axios.post(
        route('app.whatsapp-calls.calls.accept', { call: call.id }),
        {
          sdp: answer.sdp,
          sdp_type: 'answer',
        },
        { headers: { Accept: 'application/json' } }
      );
      const updated = response.data?.call;
      if (updated) onCallUpdated(updated);
      setLocalStatus('answered');
      addToast({ title: 'Call answered', variant: 'success' });
    } catch (error: any) {
      stopLocalCall();
      addToast({
        title: 'Answer failed',
        description: error?.response?.data?.message || error?.message || 'Please retry.',
        variant: 'error',
      });
    } finally {
      setBusy(null);
    }
  };

  const postAction = async (action: 'reject' | 'terminate') => {
    setBusy(action === 'reject' ? 'reject' : 'end');
    try {
      const response = await axios.post(
        route(`app.whatsapp-calls.calls.${action}`, { call: call.id }),
        {},
        { headers: { Accept: 'application/json' } }
      );
      const updated = response.data?.call;
      if (updated) onCallUpdated(updated);
      stopLocalCall();
      setLocalStatus(action === 'reject' ? 'rejected' : 'completed');
      addToast({ title: action === 'reject' ? 'Call rejected' : 'Call ended', variant: 'success' });
      onClose();
    } catch (error: any) {
      addToast({
        title: 'Call action failed',
        description: error?.response?.data?.message || 'Please retry.',
        variant: 'error',
      });
    } finally {
      setBusy(null);
    }
  };

  const displayName =
    conversation?.contact.name || call.contact_name || call.phone_number || 'WhatsApp caller';
  const displayPhone = conversation?.contact.wa_id || call.phone_number || '';
  const isAnswered = localStatus === 'answered';
  const isActionable = isIncomingActionableCall({ ...call, status: localStatus });
  const isAiRouted =
    call.routed_to === 'ai_agent' ||
    ['ai_first', 'ai_only'].includes(String(call.route_mode || ''));
  const statusText = isAnswered
    ? isAiRouted
      ? `AI connected${call.agent?.name ? `: ${call.agent.name}` : ''}`
      : 'Connected'
    : `${localStatus.replaceAll('_', ' ')} incoming`;

  return (
    <div
      className="fixed inset-0 z-[360] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
    >
      <div className="relative flex min-h-[560px] w-full max-w-sm flex-col overflow-hidden rounded-[28px] bg-slate-950 text-white shadow-2xl ring-1 ring-white/10">
        <div className="absolute inset-x-0 top-0 h-48 bg-gradient-to-b from-emerald-500/35 to-transparent" />
        <div className="relative flex flex-1 flex-col items-center px-6 py-8 text-center">
          <div className="mb-6 flex w-full items-center justify-between text-xs text-white/60">
            <span>WhatsApp call</span>
            <button
              type="button"
              onClick={onClose}
              className="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/15"
              aria-label="Hide call screen"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
          <div className="relative">
            {isActionable && (
              <span className="absolute inset-0 animate-ping rounded-full bg-emerald-400/30" />
            )}
            <div className="relative flex h-28 w-28 items-center justify-center rounded-full bg-emerald-500 text-4xl font-semibold shadow-xl shadow-emerald-950/40">
              {(String(displayName).trim().charAt(0) || 'C').toUpperCase()}
            </div>
          </div>
          <h2 className="mt-6 max-w-full truncate text-2xl font-semibold">{displayName}</h2>
          <p className="mt-1 text-sm text-white/65">{displayPhone}</p>
          <div className="mt-4 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white/80">
            {statusText}
          </div>
          {isAnswered && isAiRouted && (
            <p className="mt-3 max-w-xs text-xs leading-5 text-white/55">
              AI is handling this call. Switch routing to human first if agents should answer before
              AI.
            </p>
          )}

          <div className="mt-auto grid w-full grid-cols-3 gap-4 pb-2">
            <button
              type="button"
              disabled
              className="flex flex-col items-center gap-2 text-xs text-white/45"
            >
              <span className="flex h-14 w-14 items-center justify-center rounded-full bg-white/10">
                <Mic className="h-5 w-5" />
              </span>
              Mute
            </button>
            {isActionable ? (
              <button
                type="button"
                onClick={acceptCall}
                disabled={!callingReady || Boolean(busy)}
                className="flex flex-col items-center gap-2 text-xs font-medium text-white disabled:opacity-60"
              >
                <span className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500 shadow-lg shadow-emerald-950/50">
                  <Phone className="h-6 w-6" />
                </span>
                {busy === 'answer' ? 'Answering' : 'Answer'}
              </button>
            ) : (
              <button
                type="button"
                disabled
                className="flex flex-col items-center gap-2 text-xs font-medium text-white/55"
              >
                <span className="flex h-16 w-16 items-center justify-center rounded-full bg-white/10">
                  <Phone className="h-6 w-6" />
                </span>
                Connected
              </button>
            )}
            <button
              type="button"
              onClick={() => void postAction(isActionable ? 'reject' : 'terminate')}
              disabled={Boolean(busy)}
              className="flex flex-col items-center gap-2 text-xs font-medium text-white disabled:opacity-60"
            >
              <span className="flex h-14 w-14 items-center justify-center rounded-full bg-red-500 shadow-lg shadow-red-950/50">
                <Phone className="h-5 w-5 rotate-[135deg]" />
              </span>
              {isActionable
                ? busy === 'reject'
                  ? 'Rejecting'
                  : 'Reject'
                : busy === 'end'
                  ? 'Ending'
                  : 'End'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
