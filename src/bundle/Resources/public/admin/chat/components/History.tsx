import React, { useEffect, useMemo, useState } from 'react';

import { Button, Icon } from '@masilia-admin-kit/ids';
import { fetchThreads, renameThread } from '../runtime/threads';
import { groupThreads, whenLabel } from '../runtime/threadsView';
import type { ThreadSummary } from '../runtime/threadsView';
import type { ChatRuntime } from '../runtime/types';

type Loaded = { status: 'loading' } | { status: 'error' } | { status: 'ready'; threads: ThreadSummary[] };

interface HistoryProps {
    chat: ChatRuntime;
    /** A conversation was chosen, or a new one started: back to the conversation. */
    onDone: () => void;
}

/**
 * The user's earlier conversations on this siteaccess, grouped by day. Choosing one shows it; each can be deleted
 * after a confirmation.
 */
export function History({ chat, onDone }: HistoryProps) {
    const [loaded, setLoaded] = useState<Loaded>({ status: 'loading' });
    const [deleting, setDeleting] = useState<string | null>(null);
    const [failed, setFailed] = useState(false);
    const [renaming, setRenaming] = useState<{ id: string; value: string } | null>(null);
    const [renameFailed, setRenameFailed] = useState(false);
    const now = useMemo(() => new Date(), []);

    useEffect(() => {
        let alive = true;
        fetchThreads().then(
            (threads) => alive && setLoaded({ status: 'ready', threads }),
            () => alive && setLoaded({ status: 'error' }),
        );

        return () => {
            alive = false;
        };
    }, []);

    const remove = async (id: string) => {
        setFailed(false);
        try {
            await chat.remove(id);
            setDeleting(null);
            setLoaded((state) => (state.status === 'ready' ? { status: 'ready', threads: state.threads.filter((thread) => thread.id !== id) } : state));
        } catch {
            setFailed(true);
        }
    };

    const saveRename = async () => {
        if (renaming === null || renaming.value.trim() === '') {
            return;
        }
        setRenameFailed(false);
        try {
            const title = await renameThread(renaming.id, renaming.value);
            setLoaded((state) => (state.status === 'ready' ? { status: 'ready', threads: state.threads.map((thread) => (thread.id === renaming.id ? { ...thread, title } : thread)) } : state));
            setRenaming(null);
        } catch {
            setRenameFailed(true);
        }
    };

    return (
        <div className="masilia-chat__history">
            <div className="masilia-chat__history-new">
                <Button
                    type="primary"
                    size="small"
                    icon="message-bubble-edit"
                    onClick={() => {
                        chat.startNew();
                        onDone();
                    }}
                >
                    New conversation
                </Button>
            </div>

            {failed ? <p className="masilia-chat__error" role="alert">The conversation could not be deleted. Try again.</p> : null}
            {renameFailed ? <p className="masilia-chat__error" role="alert">The conversation could not be renamed. Try again.</p> : null}

            {loaded.status === 'loading' ? <p className="masilia-chat__history-note" role="status">Loading…</p> : null}
            {loaded.status === 'error' ? <p className="masilia-chat__error" role="alert">The history could not be loaded.</p> : null}
            {loaded.status === 'ready' && loaded.threads.length === 0 ? <p className="masilia-chat__history-note">No earlier conversation yet.</p> : null}

            {loaded.status === 'ready'
                ? groupThreads(loaded.threads, now).map((group) => (
                      <section key={group.label} className="masilia-chat__history-group" aria-label={group.label}>
                          <h3 className="masilia-chat__history-heading">{group.label}</h3>
                          <ul className="masilia-chat__history-list">
                              {group.threads.map((thread) => (
                                  <li key={thread.id} className="masilia-chat__history-item">
                                      {renaming?.id === thread.id ? (
                                          <form
                                              className="masilia-chat__history-rename"
                                              onSubmit={(event) => {
                                                  event.preventDefault();
                                                  void saveRename();
                                              }}
                                          >
                                              <label className="visually-hidden" htmlFor={`rename-${thread.id}`}>Conversation title</label>
                                              <input
                                                  id={`rename-${thread.id}`}
                                                  className="masilia-chat__history-input"
                                                  value={renaming.value}
                                                  maxLength={100}
                                                  // eslint-disable-next-line jsx-a11y/no-autofocus
                                                  autoFocus
                                                  onFocus={(event) => event.currentTarget.select()}
                                                  onChange={(event) => setRenaming({ id: thread.id, value: event.target.value })}
                                                  onKeyDown={(event) => {
                                                      if (event.key === 'Escape') {
                                                          // Cancels the edit, not the panel.
                                                          event.preventDefault();
                                                          setRenaming(null);
                                                      }
                                                  }}
                                              />
                                              <div className="masilia-chat__actions">
                                                  <Button type="secondary" size="small" onClick={() => setRenaming(null)}>Cancel</Button>
                                                  <button type="submit" className="masilia-chat__rename-save" disabled={renaming.value.trim() === ''}>Save</button>
                                              </div>
                                          </form>
                                      ) : deleting === thread.id ? (
                                          <div className="masilia-chat__history-confirm" role="alertdialog" aria-label={`Delete ${thread.title}`}>
                                              <span>Delete this conversation?</span>
                                              <div className="masilia-chat__actions">
                                                  <Button type="secondary" size="small" onClick={() => setDeleting(null)}>Keep</Button>
                                                  <Button type="primary" size="small" onClick={() => void remove(thread.id)}>Delete</Button>
                                              </div>
                                          </div>
                                      ) : (
                                          <>
                                              <button
                                                  type="button"
                                                  className="masilia-chat__history-row"
                                                  aria-current={thread.id === chat.threadId ? 'true' : undefined}
                                                  onClick={() => {
                                                      void chat.select(thread.id);
                                                      onDone();
                                                  }}
                                              >
                                                  <span className="masilia-chat__history-title">{thread.title}</span>
                                                  <span className="masilia-chat__history-when">{whenLabel(thread.updatedAt, now)}</span>
                                              </button>
                                              <button
                                                  type="button"
                                                  className="masilia-chat__icon-button masilia-chat__history-action"
                                                  title="Rename this conversation"
                                                  aria-label={`Rename conversation: ${thread.title}`}
                                                  onClick={() => {
                                                      setRenameFailed(false);
                                                      setRenaming({ id: thread.id, value: thread.title });
                                                  }}
                                              >
                                                  <Icon name="edit" size="small" />
                                              </button>
                                              <button
                                                  type="button"
                                                  className="masilia-chat__icon-button masilia-chat__history-action masilia-chat__history-delete"
                                                  title="Delete this conversation"
                                                  aria-label={`Delete conversation: ${thread.title}`}
                                                  onClick={() => setDeleting(thread.id)}
                                              >
                                                  <Icon name="trash" size="small" />
                                              </button>
                                          </>
                                      )}
                                  </li>
                              ))}
                          </ul>
                      </section>
                  ))
                : null}
        </div>
    );
}
