import { Badge } from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { ThemedIconTile } from '@/Components/UI/Elements';
import { cn } from '@/lib/utils';
import {
  BadgeIndianRupee,
  Bot,
  CalendarPlus,
  ClipboardList,
  Clock,
  DatabaseZap,
  GitBranch,
  Handshake,
  Image,
  ListChecks,
  MousePointer2,
  PanelLeftClose,
  PanelLeftOpen,
  PanelRightClose,
  PanelRightOpen,
  Plus,
  Save,
  Search,
  Send,
  Sparkles,
  Tag,
  Trash2,
  UserCheck,
  UserCog,
  Users,
  Webhook,
  Workflow,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import ReactFlow, {
  Connection,
  Controls,
  Edge,
  EdgeChange,
  MiniMap,
  Node,
  NodeChange,
  addEdge,
  applyEdgeChanges,
  applyNodeChanges,
} from 'reactflow';
import 'reactflow/dist/style.css';

interface NodeItem {
  id: number;
  type: string;
  config: any;
  sort_order: number;
  pos_x?: number | null;
  pos_y?: number | null;
}

interface EdgeItem {
  id: number;
  from_node_id: number;
  to_node_id: number;
  label?: string | null;
  sort_order?: number | null;
}

interface FlowItem {
  id: number;
  name: string;
  trigger: any;
  enabled: boolean;
  priority: number;
  nodes: NodeItem[];
  edges: EdgeItem[];
}

const nodeTypesMeta = [
  {
    type: 'action',
    label: 'Text reply',
    category: 'Messages',
    icon: Send,
    tone: 'green' as const,
    description: 'Send a plain WhatsApp reply',
  },
  {
    type: 'send_buttons',
    label: 'Buttons',
    category: 'Messages',
    icon: ListChecks,
    tone: 'blue' as const,
    description: 'Show up to 3 WhatsApp quick replies',
  },
  {
    type: 'send_template',
    label: 'Template',
    category: 'Messages',
    icon: Bot,
    tone: 'purple' as const,
    description: 'Send an approved template',
  },
  {
    type: 'send_media',
    label: 'Media',
    category: 'Messages',
    icon: Image,
    tone: 'blue' as const,
    description: 'Send an image, document, video, or audio link',
  },
  {
    type: 'send_flow',
    label: 'Form flow',
    category: 'Messages',
    icon: ClipboardList,
    tone: 'green' as const,
    description: 'Send a WhatsApp form',
  },
  {
    type: 'add_tag',
    label: 'Add tag',
    category: 'CRM',
    icon: Tag,
    tone: 'blue' as const,
    description: 'Tag the contact for segmentation',
  },
  {
    type: 'add_segment',
    label: 'Add segment',
    category: 'CRM',
    icon: Users,
    tone: 'blue' as const,
    description: 'Place contact into a lead segment',
  },
  {
    type: 'update_contact',
    label: 'Update contact',
    category: 'CRM',
    icon: UserCog,
    tone: 'amber' as const,
    description: 'Set contact fields or source',
  },
  {
    type: 'assign_agent',
    label: 'Assign agent',
    category: 'CRM',
    icon: UserCheck,
    tone: 'pink' as const,
    description: 'Route conversation to a team member',
  },
  {
    type: 'handoff',
    label: 'Handoff',
    category: 'CRM',
    icon: Handshake,
    tone: 'red' as const,
    description: 'Mark for human follow-up',
  },
  {
    type: 'create_deal',
    label: 'Create deal',
    category: 'CRM',
    icon: Workflow,
    tone: 'green' as const,
    description: 'Open a sales opportunity',
  },
  {
    type: 'create_appointment',
    label: 'Appointment',
    category: 'CRM',
    icon: CalendarPlus,
    tone: 'green' as const,
    description: 'Create a demo or service appointment',
  },
  {
    type: 'send_payment_link',
    label: 'Payment link',
    category: 'Commerce',
    icon: BadgeIndianRupee,
    tone: 'amber' as const,
    description: 'Send a checkout or payment URL',
  },
  {
    type: 'ai_agent_reply',
    label: 'AI agent',
    category: 'AI',
    icon: Sparkles,
    tone: 'purple' as const,
    description: 'Let your AI assistant reply',
  },
  {
    type: 'condition',
    label: 'Condition',
    category: 'Logic',
    icon: GitBranch,
    tone: 'blue' as const,
    description: 'Branch by message, tag, status, or time',
  },
  {
    type: 'delay',
    label: 'Delay',
    category: 'Logic',
    icon: Clock,
    tone: 'amber' as const,
    description: 'Wait before moving to the next step',
  },
  {
    type: 'sync_integration',
    label: 'Sync integration',
    category: 'Integrations',
    icon: DatabaseZap,
    tone: 'purple' as const,
    description: 'Run Meta Leads, Sheets, Calendar, or commerce sync',
  },
  {
    type: 'webhook',
    label: 'Send to URL',
    category: 'Integrations',
    icon: Webhook,
    tone: 'purple' as const,
    description: 'Call an external endpoint',
  },
];

const nodeMetaFor = (type: string, actionType?: string) =>
  nodeTypesMeta.find((item) => item.type === (type === 'action' ? actionType : type)) ||
  nodeTypesMeta[0];

function describeNode(node: NodeItem) {
  const config = node.config || {};

  if (node.type === 'condition') {
    return String(config.type || 'condition').replace(/_/g, ' ');
  }

  if (node.type === 'delay') {
    return `${Number(config.seconds || 0)} seconds`;
  }

  if (node.type === 'webhook') {
    return config.url || 'Send data to a URL';
  }

  if (config.action_type === 'send_text') {
    return config.message || 'Send text';
  }

  if (config.action_type === 'send_template') {
    return 'Send approved template';
  }

  if (config.action_type === 'send_buttons') {
    return config.body_text || 'Send quick reply buttons';
  }

  if (config.action_type === 'add_tag') {
    return config.tag_name || `Tag #${config.tag_id || ''}` || 'Add tag';
  }

  if (config.action_type === 'add_segment') {
    return config.segment_name || `Segment #${config.segment_id || ''}` || 'Add segment';
  }

  if (config.action_type === 'send_flow') {
    return config.body_text || config.meta_flow_id || 'Send WhatsApp form flow';
  }

  if (config.action_type === 'assign_agent') {
    return config.agent_id ? `Assign agent #${config.agent_id}` : 'Assign agent';
  }

  if (config.action_type === 'handoff') {
    return config.reason || 'Human handoff';
  }

  if (config.action_type === 'create_deal') {
    return config.title || 'Create deal';
  }

  if (config.action_type === 'create_appointment') {
    return config.title || 'Create appointment';
  }

  if (config.action_type === 'sync_integration') {
    return `Sync ${String(config.provider || 'integration').replace(/-/g, ' ')}`;
  }

  if (config.action_type === 'send_payment_link') {
    return 'Send payment link';
  }

  return String(config.action_type || 'action').replace(/_/g, ' ');
}

export default function FlowBuilder({
  flow,
  onEditNode,
  onAddNode,
  onDeleteNode,
  onSelectEdge,
  onSaveGraph,
  fullPage = false,
}: {
  flow: FlowItem;
  onEditNode: (node: NodeItem) => void;
  onAddNode: (type?: string) => void;
  onDeleteNode: (nodeId: number) => void;
  onSelectEdge: (edge: EdgeItem) => void;
  onSaveGraph: (payload: { nodes: NodeItem[]; edges: EdgeItem[] }) => void;
  fullPage?: boolean;
}) {
  const initialNodes: Node[] = useMemo(() => {
    return (flow.nodes || []).map((node) => ({
      id: String(node.id),
      position: {
        x: node.pos_x ?? node.sort_order * 180,
        y: node.pos_y ?? 120,
      },
      data: {
        label: nodeMetaFor(node.type, node.config?.action_type).label,
        meta: node,
      },
      style: {
        borderRadius: 14,
        border: '1px solid rgba(148, 163, 184, 0.38)',
        padding: 0,
        background: 'transparent',
        minWidth: 248,
        boxShadow: 'none',
      },
    }));
  }, [flow.nodes]);

  const initialEdges: Edge[] = useMemo(() => {
    return (flow.edges || []).map((edge) => ({
      id: String(edge.id),
      source: String(edge.from_node_id),
      target: String(edge.to_node_id),
      label: edge.label || 'next',
      animated: edge.label === 'true' || edge.label === 'false',
      style: { stroke: '#10B981', strokeWidth: 2 },
      labelStyle: { fill: '#D1D5DB', fontSize: 10, fontWeight: 700 },
      labelBgStyle: { fill: '#111827', fillOpacity: 0.92 },
      labelBgPadding: [5, 3],
      labelBgBorderRadius: 6,
    }));
  }, [flow.edges]);

  const [nodes, setNodes] = useState<Node[]>(initialNodes);
  const [edges, setEdges] = useState<Edge[]>(initialEdges);
  const [paletteQuery, setPaletteQuery] = useState('');
  const [paletteCategory, setPaletteCategory] = useState('All');
  const [paletteOpen, setPaletteOpen] = useState(() => !fullPage);
  const [summaryOpen, setSummaryOpen] = useState(false);

  useEffect(() => {
    setNodes(initialNodes);
  }, [initialNodes]);

  useEffect(() => {
    setEdges(initialEdges);
  }, [initialEdges]);

  const onNodesChange = useCallback((changes: NodeChange[]) => {
    setNodes((nds) => applyNodeChanges(changes, nds));
  }, []);

  const onEdgesChange = useCallback((changes: EdgeChange[]) => {
    setEdges((eds) => applyEdgeChanges(changes, eds));
  }, []);

  const onConnect = useCallback((connection: Connection) => {
    setEdges((eds) =>
      addEdge(
        {
          ...connection,
          id: `temp-${Date.now()}`,
          label: 'next',
          style: { stroke: '#10B981', strokeWidth: 2 },
          labelStyle: { fill: '#D1D5DB', fontSize: 10, fontWeight: 700 },
          labelBgStyle: { fill: '#111827', fillOpacity: 0.92 },
          labelBgPadding: [5, 3],
          labelBgBorderRadius: 6,
        },
        eds
      )
    );
  }, []);

  const handleSaveGraph = () => {
    const nodePayload: NodeItem[] = nodes.map((node, index) => {
      const meta = node.data?.meta as NodeItem;
      return {
        ...meta,
        sort_order: meta?.sort_order ?? index + 1,
        pos_x: Math.round(node.position.x),
        pos_y: Math.round(node.position.y),
      };
    });

    const edgePayload: EdgeItem[] = edges.map((edge, index) => ({
      id: edge.id && !Number.isNaN(Number(edge.id)) ? Number(edge.id) : 0,
      from_node_id: Number(edge.source),
      to_node_id: Number(edge.target),
      label: (edge.label as string) || 'next',
      sort_order: index + 1,
    }));

    onSaveGraph({ nodes: nodePayload, edges: edgePayload });
  };

  const paletteCategories = useMemo(
    () => ['All', ...Array.from(new Set(nodeTypesMeta.map((item) => item.category)))],
    []
  );
  const paletteItems = useMemo(() => {
    const query = paletteQuery.trim().toLowerCase();

    return nodeTypesMeta.filter((item) => {
      const matchesCategory = paletteCategory === 'All' || item.category === paletteCategory;
      const matchesQuery =
        query === '' ||
        item.label.toLowerCase().includes(query) ||
        item.description.toLowerCase().includes(query) ||
        item.category.toLowerCase().includes(query);

      return matchesCategory && matchesQuery;
    });
  }, [paletteCategory, paletteQuery]);

  const visualNodes = nodes.map((node) => {
    const meta = node.data?.meta as NodeItem;
    const nodeMeta = nodeMetaFor(meta?.type || 'action', meta?.config?.action_type);
    const Icon = nodeMeta.icon;

    return {
      ...node,
      data: {
        ...node.data,
        label: (
          <div className="w-[248px] rounded-card border border-slate-300 bg-white p-3 shadow-card ring-1 ring-white/60 dark:border-slate-600/80 dark:bg-ui-dark-surface dark:ring-white/5">
            <div className="flex items-start gap-3">
              <ThemedIconTile tone={nodeMeta.tone} size="sm">
                <Icon className="h-4 w-4" />
              </ThemedIconTile>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <p className="truncate text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                    {nodeMeta.label}
                  </p>
                  {meta?.config?.is_start && (
                    <Badge variant="info" className="px-1.5 py-0 text-[10px]">
                      Start
                    </Badge>
                  )}
                </div>
                <p className="mt-1 line-clamp-2 text-xs leading-5 text-ui-text-muted dark:text-ui-dark-text-muted">
                  {describeNode(meta)}
                </p>
              </div>
            </div>
          </div>
        ),
      },
    };
  });

  return (
    <div
      className={cn(
        'overflow-hidden bg-white dark:bg-ui-dark-surface',
        fullPage
          ? 'flex h-full min-h-0 flex-col rounded-none border-0 shadow-none'
          : 'rounded-card border border-gray-100 shadow-card dark:border-ui-dark-border'
      )}
    >
      <div
        className={cn(
          'shrink-0 flex flex-col gap-3 border-b border-gray-100 dark:border-ui-dark-border lg:flex-row lg:items-center lg:justify-between',
          fullPage ? 'px-4 py-2' : 'p-4'
        )}
      >
        <div className="min-w-0">
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-ui-text-muted dark:text-ui-dark-text-muted">
            <Workflow className="h-3.5 w-3.5" />
            Journey builder
          </div>
          <h3 className="mt-1 truncate text-base font-semibold text-ui-text dark:text-ui-dark-text">
            {flow.name}
          </h3>
          {!fullPage && (
            <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              Add steps, connect branches, then save the journey.
            </p>
          )}
        </div>
        <div className="grid grid-cols-2 gap-2 sm:flex">
          <Button
            variant="secondary"
            size="sm"
            onClick={() => setPaletteOpen((open) => !open)}
            className="hidden sm:inline-flex"
          >
            {paletteOpen ? (
              <PanelLeftClose className="h-4 w-4" />
            ) : (
              <PanelLeftOpen className="h-4 w-4" />
            )}
            Add steps
          </Button>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => setSummaryOpen((open) => !open)}
            className="hidden sm:inline-flex"
          >
            {summaryOpen ? (
              <PanelRightClose className="h-4 w-4" />
            ) : (
              <PanelRightOpen className="h-4 w-4" />
            )}
            Map
          </Button>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => onAddNode('action')}
            className="w-full sm:w-auto"
          >
            <Plus className="h-4 w-4" />
            Add step
          </Button>
          <Button size="sm" onClick={handleSaveGraph} className="w-full sm:w-auto">
            <Save className="h-4 w-4" />
            Save journey
          </Button>
        </div>
      </div>

      <div
        className={cn(
          'grid',
          fullPage
            ? cn(
                'min-h-0 flex-1',
                paletteOpen && summaryOpen && 'lg:grid-cols-[240px_minmax(0,1fr)_260px]',
                paletteOpen && !summaryOpen && 'lg:grid-cols-[240px_minmax(0,1fr)_44px]',
                !paletteOpen && summaryOpen && 'lg:grid-cols-[44px_minmax(0,1fr)_260px]',
                !paletteOpen && !summaryOpen && 'lg:grid-cols-[44px_minmax(0,1fr)_44px]'
              )
            : 'min-h-[560px] lg:grid-cols-[260px_minmax(0,1fr)_280px]'
        )}
      >
        <aside
          className={cn(
            'border-b border-gray-100 bg-gray-50/70 dark:border-ui-dark-border dark:bg-slate-950 lg:border-b-0 lg:border-r',
            paletteOpen ? 'p-3' : 'p-2',
            fullPage && 'hidden min-h-0 overflow-y-auto lg:block'
          )}
        >
          {!paletteOpen ? (
            <button
              type="button"
              onClick={() => setPaletteOpen(true)}
              className="flex h-9 w-full items-center justify-center rounded-btn border border-gray-200 bg-white text-ui-text-muted transition hover:text-ui-text dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text-muted"
              aria-label="Open steps panel"
            >
              <PanelLeftOpen className="h-4 w-4" />
            </button>
          ) : (
            <>
              <div className="mb-2 flex items-center justify-between gap-2 px-1">
                <div className="text-[10px] font-semibold uppercase tracking-wider text-ui-text-muted dark:text-ui-dark-text-muted">
                  Add step
                </div>
                <button
                  type="button"
                  onClick={() => setPaletteOpen(false)}
                  className="rounded-md p-1 text-ui-text-muted hover:bg-white hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface dark:hover:text-ui-dark-text"
                  aria-label="Collapse steps panel"
                >
                  <PanelLeftClose className="h-4 w-4" />
                </button>
              </div>
              <div className="mb-3 space-y-2">
                <div className="relative">
                  <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-ui-text-muted dark:text-ui-dark-text-muted" />
                  <input
                    value={paletteQuery}
                    onChange={(event) => setPaletteQuery(event.target.value)}
                    placeholder="Search steps"
                    className="h-8 w-full rounded-btn border border-gray-200 bg-white pl-8 pr-3 text-xs text-ui-text outline-none focus:border-ui-green focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                  />
                </div>
                <div className="flex gap-1 overflow-x-auto pb-1 ui-scrollbar">
                  {paletteCategories.map((category) => (
                    <button
                      key={category}
                      type="button"
                      onClick={() => setPaletteCategory(category)}
                      className={cn(
                        'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold transition',
                        paletteCategory === category
                          ? 'bg-ui-green text-white'
                          : 'bg-white text-ui-text-muted hover:text-ui-text dark:bg-ui-dark-surface dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text'
                      )}
                    >
                      {category}
                    </button>
                  ))}
                </div>
              </div>
              <div className="space-y-1.5">
                {paletteItems.map((item) => (
                  <button
                    key={item.type}
                    type="button"
                    onClick={() => onAddNode(item.type)}
                    className="flex w-full items-center gap-2 rounded-btn border border-gray-200 bg-white p-2 text-left transition hover:border-ui-green hover:shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:hover:border-emerald-400/60"
                  >
                    <ThemedIconTile tone={item.tone} size="sm">
                      <item.icon className="h-4 w-4" />
                    </ThemedIconTile>
                    <span className="min-w-0">
                      <span className="block truncate text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                        {item.label}
                      </span>
                      <span className="mt-0.5 block truncate text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                        {item.description}
                      </span>
                    </span>
                  </button>
                ))}
                {paletteItems.length === 0 && (
                  <div className="rounded-btn border border-dashed border-gray-200 bg-white p-3 text-xs text-ui-text-muted dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text-muted">
                    No steps match this filter.
                  </div>
                )}
              </div>
              <div className="mt-3 rounded-btn border border-gray-200 bg-white p-2 text-[11px] leading-relaxed text-ui-text-muted dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text-muted">
                <MousePointer2 className="mb-1.5 h-4 w-4 text-ui-green" />
                Double-click a step to edit. Drag handles to connect.
              </div>
            </>
          )}
        </aside>

        <div
          className={cn(
            'flow-canvas min-w-0 bg-slate-50 dark:bg-[#0B1220]',
            fullPage ? 'h-full min-h-0' : 'h-[560px]'
          )}
        >
          <ReactFlow
            nodes={visualNodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={onConnect}
            onNodeDoubleClick={(_, node) => onEditNode(node.data.meta)}
            onEdgeDoubleClick={(_, edge) => {
              const match = flow.edges.find((item) => String(item.id) === String(edge.id));
              if (match) onSelectEdge(match);
            }}
            fitView={visualNodes.length <= 12}
            defaultViewport={{ x: 48, y: 80, zoom: 0.82 }}
            minZoom={0.35}
            maxZoom={1.45}
          >
            <MiniMap
              className="hidden !h-24 !w-36 overflow-hidden rounded-btn border border-gray-200 !bg-white/90 shadow-card dark:border-ui-dark-border dark:!bg-ui-dark-surface/90 2xl:block"
              nodeStrokeColor="#10B981"
              nodeColor="#ECFDF5"
              maskColor="rgba(15, 23, 42, 0.08)"
            />
            <Controls />
          </ReactFlow>
        </div>

        <aside
          className={cn(
            'border-t border-gray-100 dark:border-ui-dark-border lg:border-l lg:border-t-0',
            summaryOpen ? 'p-3' : 'p-2',
            fullPage && 'hidden min-h-0 overflow-y-auto lg:block'
          )}
        >
          {!summaryOpen ? (
            <button
              type="button"
              onClick={() => setSummaryOpen(true)}
              className="flex h-9 w-full items-center justify-center rounded-btn border border-gray-200 text-ui-text-muted transition hover:text-ui-text dark:border-ui-dark-border dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text"
              aria-label="Open flow summary"
            >
              <PanelRightOpen className="h-4 w-4" />
            </button>
          ) : (
            <>
              <div className="mb-3 flex items-center justify-between gap-2">
                <div className="text-[10px] font-semibold uppercase tracking-wider text-ui-text-muted dark:text-ui-dark-text-muted">
                  Journey map
                </div>
                <button
                  type="button"
                  onClick={() => setSummaryOpen(false)}
                  className="rounded-md p-1 text-ui-text-muted hover:bg-gray-50 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2 dark:hover:text-ui-dark-text"
                  aria-label="Collapse flow summary"
                >
                  <PanelRightClose className="h-4 w-4" />
                </button>
              </div>
              <div className="grid grid-cols-2 gap-2">
                <div className="rounded-btn bg-gray-50 p-2.5 dark:bg-ui-dark-surface-2">
                  <p className="text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                    Steps
                  </p>
                  <p className="mt-1 text-lg font-bold text-ui-text dark:text-ui-dark-text">
                    {flow.nodes.length}
                  </p>
                </div>
                <div className="rounded-btn bg-gray-50 p-2.5 dark:bg-ui-dark-surface-2">
                  <p className="text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                    Links
                  </p>
                  <p className="mt-1 text-lg font-bold text-ui-text dark:text-ui-dark-text">
                    {flow.edges.length}
                  </p>
                </div>
              </div>

              <div className="mt-3 space-y-1.5">
                {(flow.nodes || []).map((node) => {
                  const meta = nodeMetaFor(node.type, node.config?.action_type);
                  const Icon = meta.icon;
                  return (
                    <div
                      key={node.id}
                      className="rounded-btn border border-gray-100 p-2.5 dark:border-ui-dark-border"
                    >
                      <div className="flex items-center gap-2">
                        <ThemedIconTile tone={meta.tone} size="sm">
                          <Icon className="h-4 w-4" />
                        </ThemedIconTile>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                            {meta.label}
                          </p>
                          <p className="mt-0.5 truncate text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                            {describeNode(node)}
                          </p>
                        </div>
                        <button
                          type="button"
                          onClick={() => onEditNode(node)}
                          className="rounded-md px-2 py-1 text-xs font-semibold text-ui-green-dark hover:bg-ui-green-soft dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                        >
                          Edit
                        </button>
                        <button
                          type="button"
                          onClick={() => onDeleteNode(node.id)}
                          className="rounded-md p-1.5 text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </div>
                  );
                })}
                {flow.nodes.length === 0 && (
                  <div className="rounded-btn border border-dashed border-gray-200 p-4 text-center text-xs text-ui-text-muted dark:border-ui-dark-border dark:text-ui-dark-text-muted">
                    Add the first step to make this journey runnable.
                  </div>
                )}
              </div>
            </>
          )}
        </aside>
      </div>
    </div>
  );
}
