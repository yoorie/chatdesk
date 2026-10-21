type Handler = (data: any) => void;

interface Channel {
  listen: (event: string, callback: Handler) => unknown;
  stopListening: (event: string, callback: Handler) => unknown;
}

interface Transport {
  private: (name: string) => Channel;
  leave: (name: string) => unknown;
}

export class RealtimeSubscriptions {
  private channels = new Map<
    string,
    Map<string, { channel: Channel; listeners: Set<Handler>; dispatch: Handler }>
  >();

  private transport: Transport;

  private disposed = false;

  constructor(transport: Transport) {
    this.transport = transport;
  }

  /** Transport errors (e.g. a socket that is already closing) must never break a component. */
  private safely(action: () => unknown): void {
    try {
      action();
    } catch {
      // Realtime is best-effort; the next connection re-subscribes.
    }
  }

  subscribe(name: string, event: string, callback: Handler): () => void {
    if (this.disposed) return () => {};
    let events = this.channels.get(name);
    if (!events) {
      events = new Map();
      this.channels.set(name, events);
    }
    let entry = events.get(event);
    if (!entry) {
      const listeners = new Set<Handler>();
      const dispatch: Handler = (data) => [...listeners].forEach((listener) => listener(data));
      let channel: Channel;
      try {
        channel = this.transport.private(name);
        channel.listen(event, dispatch);
      } catch {
        if (!events.size) this.channels.delete(name);
        return () => {};
      }
      entry = { channel, listeners, dispatch };
      events.set(event, entry);
    }
    // Each registration gets its own identity, even if a callback is reused.
    const listener: Handler = (data) => callback(data);
    entry.listeners.add(listener);

    const current = entry;
    return () => {
      current.listeners.delete(listener);
      if (
        this.disposed ||
        current.listeners.size ||
        this.channels.get(name) !== events ||
        events.get(event) !== current
      )
        return;
      this.safely(() => current.channel.stopListening(event, current.dispatch));
      events.delete(event);
      if (!events.size) {
        this.channels.delete(name);
        this.safely(() => this.transport.leave(name));
      }
    };
  }

  unsubscribe(name: string): void {
    if (this.disposed) return;
    this.channels.get(name)?.forEach((entry, event) => {
      entry.listeners.clear();
      this.safely(() => entry.channel.stopListening(event, entry.dispatch));
    });
    this.channels.delete(name);
    this.safely(() => this.transport.leave(name));
  }

  clear(): void {
    [...this.channels.keys()].forEach((name) => this.unsubscribe(name));
  }

  /**
   * Leave every channel while the socket is still open, then ignore late cleanups from
   * components. Call this before disconnecting: sending "unsubscribe" frames on a socket that is
   * already closing is what produced "WebSocket is already in CLOSING or CLOSED state".
   */
  dispose(): void {
    if (this.disposed) return;
    this.clear();
    this.disposed = true;
  }
}
