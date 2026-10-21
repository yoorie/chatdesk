import { appUrl } from '@/lib/appUrl';
import { friendlyStatus } from '@/lib/friendly';
import { Badge } from '@/Components/UI/Badge';
import { useToast } from '@/hooks/useToast';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { Phone } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { callSessionSdpFrom } from './helpers';
import { type Conversation, type InboxCallingSettings, type InboxCallRecord } from './types';

export function InboxCallingPanel({
  conversation,
  calling,
  calls,
  callingReady,
}: {
  conversation: Conversation;
  calling: InboxCallingSettings;
  calls: InboxCallRecord[];
  callingReady: boolean;
}) {
  const status = conversation.connection.calling_enabled
    ? 'enabled'
    : conversation.connection.calling_status || 'unknown';
  const setupUrl =
    calling.setup_url ||
    (typeof route !== 'undefined' ? route('app.whatsapp-calls.index') : appUrl('/app/whatsapp-calls'));
  const { addToast } = useToast();
  const [callBusy, setCallBusy] = useState<string | null>(null);
  const peerRef = useRef<RTCPeerConnection | null>(null);
  const localStreamRef = useRef<MediaStream | null>(null);
  const remoteAudioRef = useRef<HTMLAudioElement | null>(null);

  const contactWaId = String(conversation.contact.wa_id || '').replace(/\D+/g, '');
  const canStartOutbound = callingReady && Boolean(calling.outbound_enabled ?? true);
  const connectionRouteKey = conversation.connection.slug || conversation.connection.id;
  const aiBridgeMode = ['ai_first', 'ai_only'].includes(calling.routing_mode || '');

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

  const waitForRemoteAnswer = async (callId: number, peer: RTCPeerConnection) => {
    for (let attempt = 0; attempt < 12; attempt += 1) {
      await new Promise((resolve) => window.setTimeout(resolve, 2000));
      const response = await axios.get(route('app.whatsapp-calls.calls.show', { call: callId }), {
        headers: { Accept: 'application/json' },
      });
      const sdp = callSessionSdpFrom(response.data);
      if (sdp && peer.signalingState !== 'stable') {
        await peer.setRemoteDescription({ type: 'answer', sdp });
        addToast({ title: 'WhatsApp call connected', variant: 'success' });
        return;
      }
    }

    addToast({
      title: 'Call is waiting',
      description:
        'The call has not connected yet. It will still show in your call history.',
      variant: 'info',
    });
  };

  const checkCallPermission = async () => {
    if (!contactWaId) return;
    setCallBusy('permission-check');
    try {
      const response = await axios.post(
        route('app.whatsapp-calls.connections.call-permission.check', {
          connection: connectionRouteKey,
        }),
        { to: contactWaId },
        { headers: { Accept: 'application/json' } }
      );
      addToast({
        title: 'Permission checked',
        description:
          response.data?.permission?.status ||
          response.data?.message ||
          'WhatsApp returned the current call permission.',
        variant: 'success',
      });
    } catch (error: any) {
      addToast({
        title: 'Permission check failed',
        description:
          error?.response?.data?.message || 'Please try again. If it keeps failing, check WhatsApp calls in Settings.',
        variant: 'error',
      });
    } finally {
      setCallBusy(null);
    }
  };

  const requestCallPermission = async () => {
    if (!contactWaId) return;
    setCallBusy('permission-request');
    try {
      await axios.post(
        route('app.whatsapp-calls.connections.call-permission.request', {
          connection: connectionRouteKey,
        }),
        {
          to: contactWaId,
          message: 'Can we call you on WhatsApp about this conversation?',
        },
        { headers: { Accept: 'application/json' } }
      );
      addToast({ title: 'Call permission request sent', variant: 'success' });
    } catch (error: any) {
      addToast({
        title: 'Permission request failed',
        description:
          error?.response?.data?.message || 'Please try again. If it keeps failing, check WhatsApp calls in Settings.',
        variant: 'error',
      });
    } finally {
      setCallBusy(null);
    }
  };

  const startOutboundCall = async () => {
    if (!canStartOutbound || !contactWaId) return;
    setCallBusy('start');
    try {
      if (aiBridgeMode) {
        await axios.post(
          route('app.whatsapp-calls.connections.calls.start', { connection: connectionRouteKey }),
          {
            to: contactWaId,
            bridge_mode: 'ai',
          },
          { headers: { Accept: 'application/json' } }
        );
        addToast({
          title: 'AI voice call queued',
          description: 'The voice bridge worker will connect the WhatsApp call.',
          variant: 'success',
        });
        return;
      }

      const peer = await createPeer();
      const offer = await peer.createOffer({ offerToReceiveAudio: true });
      await peer.setLocalDescription(offer);
      const response = await axios.post(
        route('app.whatsapp-calls.connections.calls.start', { connection: connectionRouteKey }),
        {
          to: contactWaId,
          sdp: offer.sdp,
          sdp_type: 'offer',
          bridge_mode: 'browser',
        },
        { headers: { Accept: 'application/json' } }
      );
      addToast({
        title: 'WhatsApp call started',
        description: 'Connecting the call...',
        variant: 'success',
      });
      const immediateAnswer = callSessionSdpFrom(response.data);
      if (immediateAnswer) {
        await peer.setRemoteDescription({ type: 'answer', sdp: immediateAnswer });
      } else if (response.data?.call?.id) {
        void waitForRemoteAnswer(Number(response.data.call.id), peer);
      }
    } catch (error: any) {
      stopLocalCall();
      addToast({
        title: 'WhatsApp call failed',
        description:
          error?.response?.data?.message ||
          error?.message ||
          'Please retry after checking microphone permission.',
        variant: 'error',
      });
    } finally {
      setCallBusy(null);
    }
  };

  const acceptInboundCall = async (call: InboxCallRecord) => {
    const offerSdp = callSessionSdpFrom(call);
    if (!offerSdp) {
      addToast({
        title: 'Cannot answer yet',
        description: 'This call could not be connected. Please ask the customer to call again.',
        variant: 'warning',
      });
      return;
    }

    setCallBusy(`accept-${call.id}`);
    try {
      const peer = await createPeer();
      await peer.setRemoteDescription({ type: 'offer', sdp: offerSdp });
      const answer = await peer.createAnswer();
      await peer.setLocalDescription(answer);
      await axios.post(
        route('app.whatsapp-calls.calls.accept', { call: call.id }),
        {
          sdp: answer.sdp,
          sdp_type: 'answer',
        },
        { headers: { Accept: 'application/json' } }
      );
      addToast({ title: 'WhatsApp call answered', variant: 'success' });
    } catch (error: any) {
      stopLocalCall();
      addToast({
        title: 'Answer failed',
        description: error?.response?.data?.message || error?.message || 'Please retry.',
        variant: 'error',
      });
    } finally {
      setCallBusy(null);
    }
  };

  const postCallAction = async (call: InboxCallRecord, action: 'reject' | 'terminate') => {
    setCallBusy(`${action}-${call.id}`);
    try {
      await axios.post(
        route(`app.whatsapp-calls.calls.${action}`, { call: call.id }),
        {},
        { headers: { Accept: 'application/json' } }
      );
      if (action === 'terminate') stopLocalCall();
      addToast({ title: action === 'reject' ? 'Call rejected' : 'Call ended', variant: 'success' });
    } catch (error: any) {
      addToast({
        title: 'Call action failed',
        description: error?.response?.data?.message || 'Please retry.',
        variant: 'error',
      });
    } finally {
      setCallBusy(null);
    }
  };

  useEffect(() => stopLocalCall, [conversation.id]);

  return (
    <div className="space-y-3">
      <div className="rounded-lg border border-gray-100 bg-white p-3 dark:border-ui-dark-border dark:bg-slate-800">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
              WhatsApp Calling
            </p>
            <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              {conversation.connection.name}
            </p>
          </div>
          <Badge
            variant={callingReady ? 'success' : status === 'not_eligible' ? 'danger' : 'warning'}
          >
            {callingReady ? 'ready' : status.replaceAll('_', ' ')}
          </Badge>
        </div>
        <dl className="mt-3 space-y-2 text-xs">
          <div className="flex justify-between gap-3">
            <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Module</dt>
            <dd className="font-medium text-ui-text dark:text-ui-dark-text">
              {calling.enabled ? 'Enabled' : 'Off'}
            </dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Route</dt>
            <dd className="text-right font-medium text-ui-text dark:text-ui-dark-text">
              {(calling.routing_mode || 'ai_first').replaceAll('_', ' ')}
            </dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">
              Calls webhook
            </dt>
            <dd className="font-medium text-ui-text dark:text-ui-dark-text">
              {conversation.connection.calling_webhook_subscribed ? 'Subscribed' : 'Not subscribed'}
            </dd>
          </div>
        </dl>
        <div className="mt-3 grid gap-2">
          {callingReady ? (
            <div className="rounded-btn bg-emerald-50 px-3 py-2 text-center text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20">
              WhatsApp Calling enabled
            </div>
          ) : (
            <Link
              href={setupUrl}
              className="inline-flex h-9 items-center justify-center rounded-btn bg-ui-green px-3 text-sm font-medium text-white transition hover:bg-ui-green-dark"
            >
              Configure WhatsApp calling
            </Link>
          )}
          {callingReady && (
            <>
              <button
                type="button"
                onClick={checkCallPermission}
                disabled={Boolean(callBusy)}
                className="inline-flex h-9 items-center justify-center rounded-btn bg-white px-3 text-sm font-medium text-ui-text ring-1 ring-inset ring-gray-200 transition hover:bg-gray-50 disabled:opacity-60 dark:bg-slate-900 dark:text-ui-dark-text dark:ring-ui-dark-border dark:hover:bg-slate-800"
              >
                Check call permission
              </button>
              <button
                type="button"
                onClick={requestCallPermission}
                disabled={Boolean(callBusy)}
                className="inline-flex h-9 items-center justify-center rounded-btn bg-white px-3 text-sm font-medium text-ui-text ring-1 ring-inset ring-gray-200 transition hover:bg-gray-50 disabled:opacity-60 dark:bg-slate-900 dark:text-ui-dark-text dark:ring-ui-dark-border dark:hover:bg-slate-800"
              >
                Request WhatsApp call
              </button>
              <button
                type="button"
                onClick={startOutboundCall}
                disabled={!canStartOutbound || Boolean(callBusy)}
                className="inline-flex h-9 items-center justify-center gap-2 rounded-btn bg-ui-green px-3 text-sm font-medium text-white transition hover:bg-ui-green-dark disabled:opacity-60"
              >
                <Phone className="h-4 w-4" />
                Start WhatsApp call
              </button>
            </>
          )}
          {callingReady && (
            <Link
              href={setupUrl}
              className="inline-flex h-9 items-center justify-center rounded-btn bg-white px-3 text-sm font-medium text-ui-text ring-1 ring-inset ring-gray-200 transition hover:bg-gray-50 dark:bg-slate-900 dark:text-ui-dark-text dark:ring-ui-dark-border dark:hover:bg-slate-800"
            >
              Calling settings
            </Link>
          )}
        </div>
      </div>

      {calls.length === 0 ? (
        <div className="rounded-lg border border-dashed border-gray-200 p-5 text-center dark:border-ui-dark-border">
          <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-ui-text-muted dark:bg-slate-800 dark:text-ui-dark-text-muted">
            <Phone className="h-4 w-4" />
          </div>
          <p className="mt-2 text-xs font-medium text-ui-text dark:text-ui-dark-text">
            No WhatsApp call events
          </p>
          <p className="mt-1 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
            Incoming call webhooks for this contact will appear here.
          </p>
        </div>
      ) : (
        <div className="space-y-2">
          {calls.map((call) => (
            <div
              key={call.id}
              className="rounded-lg border border-gray-100 bg-white p-3 text-xs dark:border-ui-dark-border dark:bg-slate-800"
            >
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="font-semibold capitalize text-ui-text dark:text-ui-dark-text">
                    {call.direction} call
                  </p>
                  <p className="mt-1 text-ui-text-muted dark:text-ui-dark-text-muted">
                    {call.created_at ? new Date(call.created_at).toLocaleString() : '-'}
                  </p>
                </div>
                <Badge
                  variant={
                    ['completed', 'answered'].includes(call.status)
                      ? 'success'
                      : ['failed', 'missed', 'rejected'].includes(call.status)
                        ? 'danger'
                        : 'warning'
                  }
                >
                  {friendlyStatus(call.status)}
                </Badge>
              </div>
              <div className="mt-2 text-ui-text-muted dark:text-ui-dark-text-muted">
                Routed to {call.agent?.name || call.routed_to || call.route_mode || 'not routed'}
                {call.duration_seconds ? ` · ${Math.round(call.duration_seconds / 60)} min` : ''}
              </div>
              {callingReady &&
                ['received', 'ringing', 'queued', 'initiated', 'answered'].includes(
                  call.status
                ) && (
                  <div className="mt-3 flex flex-wrap gap-2">
                    {call.direction === 'inbound' &&
                      ['received', 'ringing', 'queued'].includes(call.status) && (
                        <>
                          <button
                            type="button"
                            onClick={() => acceptInboundCall(call)}
                            disabled={Boolean(callBusy)}
                            className="inline-flex h-8 items-center rounded-btn bg-ui-green px-3 text-[11px] font-medium text-white disabled:opacity-60"
                          >
                            Answer
                          </button>
                          <button
                            type="button"
                            onClick={() => postCallAction(call, 'reject')}
                            disabled={Boolean(callBusy)}
                            className="inline-flex h-8 items-center rounded-btn bg-white px-3 text-[11px] font-medium text-red-700 ring-1 ring-inset ring-red-200 disabled:opacity-60 dark:bg-slate-900 dark:text-red-300 dark:ring-red-500/30"
                          >
                            Reject
                          </button>
                        </>
                      )}
                    {['initiated', 'answered', 'ringing'].includes(call.status) && (
                      <button
                        type="button"
                        onClick={() => postCallAction(call, 'terminate')}
                        disabled={Boolean(callBusy)}
                        className="inline-flex h-8 items-center rounded-btn bg-white px-3 text-[11px] font-medium text-ui-text ring-1 ring-inset ring-gray-200 disabled:opacity-60 dark:bg-slate-900 dark:text-ui-dark-text dark:ring-ui-dark-border"
                      >
                        End call
                      </button>
                    )}
                  </div>
                )}
              {call.summary && (
                <p className="mt-2 line-clamp-2 text-ui-text-muted dark:text-ui-dark-text-muted">
                  {call.summary}
                </p>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
