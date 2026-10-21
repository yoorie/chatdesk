import { brandName } from '../../lib/brand';
import TextInput from '@/Components/TextInput';
import Button from '@/Components/UI/Button';
import { Card, CardContent } from '@/Components/UI/Card';
import { Modal, ThemedIconTile } from '@/Components/UI/Elements';
import { useConfirm } from '@/hooks/useConfirm';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Bot, ChevronDown, ChevronUp, Play, Workflow } from 'lucide-react';
import { useEffect, useState } from 'react';
import FlowBuilder from './FlowBuilder';
import { AutomationOptions, BotDetail, NodeConfigFields } from './Index';

function actionLabel(actionType?: string) {
  return String(actionType || 'send_text').replace(/_/g, ' ');
}

export default function ChatbotBuilder({
  bot,
  automationOptions = { agents: [], tags: [], templates: [], lists: [] },
}: {
  account: any;
  bot: BotDetail;
  connections?: Array<{ id: number; name: string }>;
  automationOptions?: AutomationOptions;
}) {
  const confirm = useConfirm();
  const initialFlowId =
    Number(new URLSearchParams(window.location.search).get('flow') || bot.flows?.[0]?.id || 0) ||
    null;
  const [activeFlowId, setActiveFlowId] = useState<number | null>(initialFlowId);
  const [nodeEditor, setNodeEditor] = useState<{
    node: BotDetail['flows'][number]['nodes'][number];
    config: Record<string, any>;
    type: string;
  } | null>(null);
  const [edgeEditor, setEdgeEditor] = useState<{
    edge: BotDetail['flows'][number]['edges'][number];
    label: string;
  } | null>(null);
  const [testMessage, setTestMessage] = useState('pricing');
  const [testResult, setTestResult] = useState<any | null>(null);
  const [testing, setTesting] = useState(false);
  const [cooldownMinutes, setCooldownMinutes] = useState(0);
  const [triggerSource, setTriggerSource] = useState('any');
  const [ctwaSourceIds, setCtwaSourceIds] = useState('');
  const [testPanelOpen, setTestPanelOpen] = useState(false);

  useEffect(() => {
    if (!bot.flows.some((flow) => flow.id === activeFlowId)) {
      setActiveFlowId(bot.flows?.[0]?.id ?? null);
    }
  }, [bot.flows, activeFlowId]);

  const activeFlow = bot.flows?.length
    ? (bot.flows.find((flow) => flow.id === activeFlowId) ?? bot.flows[0])
    : null;

  useEffect(() => {
    setCooldownMinutes(Number(activeFlow?.trigger?.cooldown_minutes ?? 0));
    setTriggerSource(String(activeFlow?.trigger?.source ?? 'any'));
    setCtwaSourceIds(
      Array.isArray(activeFlow?.trigger?.ctwa_source_ids)
        ? activeFlow.trigger.ctwa_source_ids.join(', ')
        : ''
    );
  }, [
    activeFlow?.id,
    activeFlow?.trigger?.cooldown_minutes,
    activeFlow?.trigger?.source,
    activeFlow?.trigger?.ctwa_source_ids,
  ]);

  const selectFlow = (flowId: number) => {
    setActiveFlowId(flowId);
    router.get(
      route('app.chatbots.builder', { bot: bot.id, flow: flowId }),
      {},
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      }
    );
  };

  const defaultNodeConfig = (type = 'action') => {
    if (type === 'condition')
      return { type: 'text_contains', value: 'help', case_sensitive: false };
    if (type === 'delay') return { seconds: 60 };
    if (type === 'webhook') return { url: 'https://example.com/webhook', method: 'POST' };
    if (type === 'send_buttons')
      return {
        action_type: 'send_buttons',
        header_text: `${brandName()}`,
        body_text: 'Choose the next step.',
        buttons: [
          { id: 'pricing', text: 'Pricing' },
          { id: 'payment', text: 'Payment' },
          { id: 'ai', text: 'AI agent' },
        ],
      };
    if (type === 'send_template')
      return {
        action_type: 'send_template',
        template_id: automationOptions.templates[0]?.id || '',
      };
    if (type === 'send_media')
      return {
        action_type: 'send_media',
        media_type: 'image',
        media_url: 'https://placehold.co/800x400.png',
        caption: `${brandName()} feature overview`,
      };
    if (type === 'send_list')
      return { action_type: 'send_list', list_id: automationOptions.lists[0]?.id || '' };
    if (type === 'assign_agent')
      return { action_type: 'assign_agent', agent_id: automationOptions.agents[0]?.id || '' };
    if (type === 'add_tag')
      return { action_type: 'add_tag', tag_id: automationOptions.tags[0]?.id || '', tag_name: '' };
    if (type === 'update_contact')
      return { action_type: 'update_contact', status: 'active', source: 'automation' };
    if (type === 'create_deal')
      return {
        action_type: 'create_deal',
        title: 'WhatsApp lead',
        stage: 'new',
        value: 0,
        currency: 'INR',
        source: 'automation',
      };
    if (type === 'create_appointment')
      return {
        action_type: 'create_appointment',
        title: `${brandName()} demo call`,
        minutes_from_now: 60,
        duration_minutes: 30,
        type: 'Demo',
        reminder_enabled: true,
        reminder_minutes_before: 30,
      };
    if (type === 'handoff')
      return {
        action_type: 'handoff',
        priority: 'high',
        status: 'open',
        reason: 'Needs human help',
        agent_id: automationOptions.agents[0]?.id || '',
      };

    return {
      action_type: 'send_text',
      message: 'Thanks for messaging us. A team member will reply shortly.',
    };
  };

  const addNode = (type = 'action') => {
    if (!activeFlow) return;
    const nodeType = ['condition', 'delay', 'webhook'].includes(type) ? type : 'action';

    router.post(
      route('app.chatbots.nodes.store', { flow: activeFlow.id }),
      {
        type: nodeType,
        config: defaultNodeConfig(type),
        sort_order: activeFlow.nodes.length + 1,
        pos_x: 320 + activeFlow.nodes.length * 60,
        pos_y: 160 + activeFlow.nodes.length * 40,
      },
      {
        preserveScroll: true,
        only: ['bot', 'flash', 'errors'],
      }
    );
  };

  const saveGraph = (payload: {
    nodes: BotDetail['flows'][number]['nodes'];
    edges: BotDetail['flows'][number]['edges'];
  }) => {
    if (!activeFlow) return;

    router.patch(
      route('app.chatbots.flows.update', { flow: activeFlow.id }),
      {
        nodes: payload.nodes,
        edges: payload.edges,
      },
      {
        preserveScroll: true,
        only: ['bot', 'flash', 'errors'],
      }
    );
  };

  const saveSafety = () => {
    if (!activeFlow) return;

    router.patch(
      route('app.chatbots.flows.update', { flow: activeFlow.id }),
      {
        trigger: {
          ...(activeFlow.trigger || {}),
          cooldown_minutes: Math.max(0, Number(cooldownMinutes) || 0),
          source: triggerSource,
          ctwa_source_ids: ctwaSourceIds
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean),
        },
      },
      {
        preserveScroll: true,
        only: ['bot', 'flash', 'errors'],
      }
    );
  };

  const deleteNode = async (nodeId: number) => {
    const confirmed = await confirm({
      title: 'Delete this step?',
      message: 'This step will be removed from the chatbot.',
      confirmText: 'Delete step',
      cancelText: 'Cancel',
      variant: 'danger',
    });
    if (!confirmed) return;

    router.delete(route('app.chatbots.nodes.destroy', { node: nodeId }), {
      preserveScroll: true,
      only: ['bot', 'flash', 'errors'],
    });
  };

  const setNodeConfig = (key: string, value: any) => {
    setNodeEditor((current) =>
      current ? { ...current, config: { ...current.config, [key]: value } } : current
    );
  };

  const saveNodeConfig = () => {
    if (!nodeEditor) return;
    const nodeType = ['condition', 'delay', 'webhook'].includes(nodeEditor.type)
      ? nodeEditor.type
      : 'action';

    router.patch(
      route('app.chatbots.nodes.update', { node: nodeEditor.node.id }),
      {
        type: nodeType,
        config: nodeEditor.config,
      },
      {
        preserveScroll: true,
        only: ['bot', 'flash', 'errors'],
        onSuccess: () => setNodeEditor(null),
      }
    );
  };

  const saveEdgeLabel = () => {
    if (!edgeEditor) return;

    router.patch(
      route('app.chatbots.edges.update', { edge: edgeEditor.edge.id }),
      {
        label: edgeEditor.label,
      },
      {
        preserveScroll: true,
        only: ['bot', 'flash', 'errors'],
        onSuccess: () => setEdgeEditor(null),
      }
    );
  };

  const runTest = () => {
    router.post(
      route('app.chatbots.test', { bot: bot.id }),
      {},
      {
        preserveScroll: true,
        only: ['bot', 'flash', 'errors'],
      }
    );
  };

  const simulate = async () => {
    setTesting(true);
    setTestResult(null);
    try {
      const token =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
      const response = await fetch(route('app.chatbots.simulate', { bot: bot.id }), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({
          message: testMessage,
          flow_id: activeFlow?.id,
        }),
      });
      setTestResult(await response.json());
    } finally {
      setTesting(false);
    }
  };

  return (
    <AppShell fullscreen>
      <Head title={`${bot.name} builder`} />
      <div className="flex h-[100dvh] min-h-0 flex-col bg-ui-bg dark:bg-ui-dark-bg">
        <div className="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-gray-100 bg-white px-4 dark:border-ui-dark-border dark:bg-ui-dark-surface">
          <div className="flex min-w-0 items-center gap-2">
            <Link href={route('app.chatbots.index', {})}>
              <Button variant="ghost" size="sm" aria-label="Back to chatbots">
                <ArrowLeft className="h-4 w-4" />
              </Button>
            </Link>
            <ThemedIconTile tone="green" size="sm">
              <Workflow className="h-4 w-4" />
            </ThemedIconTile>
            <div className="min-w-0">
              <h1 className="truncate text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                {bot.name}
              </h1>
            </div>
          </div>
          <Button type="button" variant="secondary" onClick={runTest}>
            <Play className="h-4 w-4" />
            Test
          </Button>
        </div>

        {bot.flows.length > 1 && (
          <div className="ui-scrollbar flex h-12 shrink-0 gap-2 overflow-x-auto border-b border-gray-100 bg-white px-4 py-2 dark:border-ui-dark-border dark:bg-ui-dark-surface">
            {bot.flows.map((flow) => (
              <button
                key={flow.id}
                type="button"
                onClick={() => selectFlow(flow.id)}
                className={`inline-flex h-8 shrink-0 items-center gap-2 rounded-btn px-3 text-xs font-semibold transition ${
                  activeFlow?.id === flow.id
                    ? 'bg-ui-green text-white'
                    : 'bg-gray-100 text-ui-text-muted hover:text-ui-text dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text'
                }`}
              >
                <span
                  className={`h-2 w-2 rounded-full ${flow.enabled ? 'bg-current' : 'bg-gray-400'}`}
                />
                {flow.name}
              </button>
            ))}
          </div>
        )}

        <div
          className={
            nodeEditor ? 'grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_360px]' : 'min-h-0 flex-1'
          }
        >
          {activeFlow ? (
            <FlowBuilder
              flow={activeFlow}
              onAddNode={addNode}
              onEditNode={(node) =>
                setNodeEditor({ node, type: node.type, config: { ...(node.config || {}) } })
              }
              onDeleteNode={deleteNode}
              onSelectEdge={(edge) => setEdgeEditor({ edge, label: edge.label || 'next' })}
              onSaveGraph={saveGraph}
              fullPage
            />
          ) : (
            <Card className="border-transparent dark:border-slate-700/80">
              <CardContent className="flex h-full min-h-[520px] flex-col items-center justify-center text-center">
                <Bot className="mb-3 h-8 w-8 text-ui-text-muted" />
                <p className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                  No flow selected
                </p>
              </CardContent>
            </Card>
          )}
          {nodeEditor && (
            <aside className="fixed inset-x-0 bottom-0 z-40 max-h-[78dvh] min-h-0 overflow-y-auto border-t border-gray-100 bg-white p-4 shadow-2xl dark:border-ui-dark-border dark:bg-ui-dark-surface lg:static lg:block lg:max-h-none lg:border-l lg:border-t-0 lg:shadow-none">
              <div className="mb-4 flex items-start justify-between gap-3">
                <div>
                  <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-ui-green-dark dark:text-emerald-300">
                    Node inspector
                  </p>
                  <h2 className="mt-1 text-base font-semibold text-ui-text dark:text-ui-dark-text">
                    {nodeEditor.type === 'action'
                      ? actionLabel(nodeEditor.config?.action_type)
                      : nodeEditor.type}
                  </h2>
                </div>
                <Button type="button" variant="ghost" size="sm" onClick={() => setNodeEditor(null)}>
                  Close
                </Button>
              </div>
              <NodeConfigFields
                editor={nodeEditor}
                options={automationOptions}
                onChange={setNodeConfig}
              />
              <div className="mt-4 flex justify-end gap-2">
                <Button type="button" variant="secondary" onClick={() => setNodeEditor(null)}>
                  Cancel
                </Button>
                <Button type="button" onClick={saveNodeConfig}>
                  Save node
                </Button>
              </div>
            </aside>
          )}
        </div>

        <div className="shrink-0 border-t border-gray-100 bg-white dark:border-ui-dark-border dark:bg-ui-dark-surface">
          <div className="flex flex-col gap-2 px-4 py-2 xl:flex-row xl:items-center">
            <div className="flex min-w-0 flex-1 items-center gap-2">
              <TextInput
                className="h-9"
                value={testMessage}
                onChange={(event) => setTestMessage(event.target.value)}
                placeholder="Simulate an inbound message, e.g. pricing"
              />
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={simulate}
                disabled={testing}
                className="shrink-0"
              >
                {testing ? 'Testing...' : 'Simulate'}
              </Button>
            </div>
            <button
              type="button"
              onClick={() => setTestPanelOpen((open) => !open)}
              className="inline-flex h-9 items-center justify-center gap-2 rounded-btn border border-gray-200 px-3 text-xs font-semibold text-ui-text-muted transition hover:text-ui-text dark:border-ui-dark-border dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text"
            >
              Trigger settings
              {testPanelOpen ? (
                <ChevronDown className="h-4 w-4" />
              ) : (
                <ChevronUp className="h-4 w-4" />
              )}
            </button>
            {testResult && (
              <div className="min-w-0 rounded-btn bg-gray-50 px-3 py-2 text-xs text-ui-text-muted dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted xl:w-[260px]">
                {(testResult.trace || []).length} flow trace
                {(testResult.trace || []).length === 1 ? '' : 's'}
              </div>
            )}
          </div>
          {testPanelOpen && (
            <div className="grid gap-3 border-t border-gray-100 px-4 py-3 dark:border-ui-dark-border xl:grid-cols-[150px_190px_minmax(220px,1fr)_auto_minmax(300px,1.4fr)] xl:items-start">
              <label className="text-xs font-semibold text-ui-text-muted dark:text-ui-dark-text-muted">
                Cooldown minutes
                <TextInput
                  className="mt-1 h-9"
                  type="number"
                  min="0"
                  value={cooldownMinutes}
                  onChange={(event) => setCooldownMinutes(Number(event.target.value))}
                />
              </label>
              <label className="text-xs font-semibold text-ui-text-muted dark:text-ui-dark-text-muted">
                Source
                <select
                  className="mt-1 h-9 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                  value={triggerSource}
                  onChange={(event) => setTriggerSource(event.target.value)}
                >
                  <option value="any">Any source</option>
                  <option value="ctwa">Click-to-WhatsApp ad</option>
                  <option value="webhook">Organic WhatsApp</option>
                  <option value="meta_lead">Meta lead</option>
                  <option value="manual">Manual</option>
                </select>
              </label>
              <label className="text-xs font-semibold text-ui-text-muted dark:text-ui-dark-text-muted">
                CTWA ad/post IDs
                <TextInput
                  className="mt-1 h-9"
                  value={ctwaSourceIds}
                  onChange={(event) => setCtwaSourceIds(event.target.value)}
                  placeholder="optional, comma separated"
                />
              </label>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={saveSafety}
                disabled={!activeFlow}
                className="mt-5"
              >
                Save trigger
              </Button>
              <div className="max-h-28 overflow-y-auto rounded-btn bg-gray-50 p-3 text-xs text-ui-text-muted dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted">
                {!testResult ? (
                  'Simulation trace will appear here.'
                ) : (
                  <div className="space-y-2">
                    {(testResult.trace || []).map((flow: any) => (
                      <div key={flow.flow_id}>
                        <div
                          className={
                            flow.matched
                              ? 'font-semibold text-ui-green-dark dark:text-emerald-300'
                              : 'font-semibold'
                          }
                        >
                          {flow.flow_name}: {flow.matched ? 'matched' : 'skipped'}
                        </div>
                        {(flow.steps || []).map((step: any, index: number) => (
                          <div key={`${flow.flow_id}-${index}`} className="pl-3">
                            {index + 1}. {step.label} - {step.detail}
                          </div>
                        ))}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      <Modal
        open={Boolean(edgeEditor)}
        onClose={() => setEdgeEditor(null)}
        title="Edit branch label"
        description="Name this connection so the flow is easier to scan."
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setEdgeEditor(null)}>
              Cancel
            </Button>
            <Button type="button" onClick={saveEdgeLabel}>
              Save label
            </Button>
          </>
        }
      >
        <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
          Label
        </label>
        <TextInput
          value={edgeEditor?.label || ''}
          onChange={(event) =>
            setEdgeEditor((current) =>
              current ? { ...current, label: event.target.value } : current
            )
          }
          placeholder="next"
        />
      </Modal>
    </AppShell>
  );
}
