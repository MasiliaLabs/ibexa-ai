import { ComposerPrimitive, useAui, useAuiState } from '@assistant-ui/react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';

import { Icon } from '../../shared/ids';
import { MAX_IMAGE_BYTES, encodeAttachments, formatBytes, isImageFile, isTextFile, toTextAttachment, tooMuch } from '../attachments';
import type { Attachment } from '../attachments';
import { encodeMentions, insertMention, labelFor, mentionTrigger } from '../mentions';
import type { Picked, Trigger } from '../mentions';
import { useMentionSearch } from '../mentionSearch';
import { pageTitle } from '../pageTitle';
import { runContext } from '../runtime/pageContext';
import { uploadImage } from '../runtime/uploads';
import { setSharesPage, useSharesPage } from '../runtime/sharePage';
import type { ChatRuntime } from '../runtime/types';
import { MentionList } from './MentionList';
import type { MentionOption } from './MentionList';

const LIST_ID = 'masilia-chat-mentions';

/** The page the editor has open, as something that can be mentioned. */
function openPage(): MentionOption | null {
    const page = runContext(window.location.pathname)[0];
    if (page === undefined) {
        return null;
    }
    const ids = JSON.parse(page.value) as { contentId: number; locationId: number };

    return { contentId: ids.contentId, locationId: ids.locationId, name: pageTitle() ?? `Content ${ids.contentId}`, type: 'page', note: 'this page' };
}

/**
 * The message box. Enter sends, Shift+Enter starts a new line, "@" mentions a content. While the assistant
 * answers, send becomes stop. While a change awaits a decision the box is disabled: the server takes no new message
 * until it is decided.
 */
export function Composer({ chat }: { chat: ChatRuntime }) {
    const aui = useAui();
    const running = useAuiState((state) => state.thread.isRunning);
    const text = useAuiState((state) => state.composer.text);
    const disabled = chat.useBlocked();

    const input = useRef<HTMLTextAreaElement>(null);
    const [picked, setPicked] = useState<Picked[]>([]);
    const [trigger, setTrigger] = useState<Trigger | null>(null);
    const [active, setActive] = useState(0);
    const [attachments, setAttachments] = useState<Attachment[]>([]);
    const [uploading, setUploading] = useState(0);
    const [attachError, setAttachError] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);
    const picker = useRef<HTMLInputElement>(null);
    const page = useMemo(openPage, []);
    const search = useMentionSearch(trigger?.query ?? null);

    const options = useMemo<MentionOption[]>(() => {
        if (trigger === null) {
            return [];
        }

        return trigger.query === '' ? (page !== null ? [page] : []) : search.items;
    }, [trigger, page, search.items]);
    // The list is open for an "@", and tells what it is doing when it has nothing to offer.
    const open = trigger !== null;

    useEffect(() => setActive(0), [trigger?.query]);

    const refresh = (element: HTMLTextAreaElement) => setTrigger(mentionTrigger(element.value, element.selectionStart ?? element.value.length));

    const choose = (option: MentionOption) => {
        if (trigger === null) {
            return;
        }
        const label = labelFor(option.name, option.contentId, picked);
        const result = insertMention(text, trigger, label);
        setPicked((previous) => [...previous.filter((item) => item.contentId !== option.contentId), { contentId: option.contentId, locationId: option.locationId, name: option.name, label }]);
        aui.composer().setText(result.text);
        setTrigger(null);
        window.requestAnimationFrame(() => {
            input.current?.focus();
            input.current?.setSelectionRange(result.caret, result.caret);
        });
    };

    const addFiles = async (files: readonly File[]) => {
        setAttachError(null);
        const added: Attachment[] = [];
        for (const file of files) {
            if (isImageFile(file)) {
                if (file.size > MAX_IMAGE_BYTES) {
                    setAttachError(`${file.name} is too large (${formatBytes(file.size)}; up to ${formatBytes(MAX_IMAGE_BYTES)}).`);

                    return;
                }
                // Checked before it is uploaded: the files already chosen count too.
                const early = tooMuch(attachments, [...added, { kind: 'image', id: '', name: file.name, bytes: file.size, width: 0, height: 0 }]);
                if (early !== null) {
                    setAttachError(early);

                    return;
                }
                setUploading((count) => count + 1);
                try {
                    added.push(await uploadImage(file));
                } catch (error) {
                    setAttachError(error instanceof Error ? error.message : 'The image could not be sent.');

                    return;
                } finally {
                    setUploading((count) => count - 1);
                }
                continue;
            }
            if (!isTextFile(file)) {
                setAttachError(`${file.name} cannot be attached: images (JPEG, PNG, GIF, WebP) and text documents (txt, md, csv, json, html…) only.`);

                return;
            }
            const read = toTextAttachment(file, await file.text());
            if (!read.ok) {
                setAttachError(read.reason);

                return;
            }
            added.push(read.attachment);
        }
        const refusal = tooMuch(attachments, added);
        if (refusal !== null) {
            setAttachError(refusal);

            return;
        }
        setAttachments((previous) => [...previous, ...added]);
    };

    const forget = (attachment: Attachment) => {
        if (attachment.kind === 'image' && attachment.previewUrl !== undefined) {
            URL.revokeObjectURL(attachment.previewUrl);
        }
    };

    const submit = () => {
        const current = aui.composer().getState().text;
        if (disabled || running || uploading > 0 || (current.trim() === '' && attachments.length === 0)) {
            return;
        }
        // The mentions and the documents become what the agent and the history understand: the box showed "@label".
        aui.composer().setText(encodeAttachments(encodeMentions(current, picked), attachments));
        aui.composer().send();
        setPicked([]);
        attachments.forEach(forget);
        setAttachments([]);
        setAttachError(null);
        setTrigger(null);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.nativeEvent.isComposing) {
            return;
        }
        if (open && options.length > 0) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                setActive((index) => (index + (event.key === 'ArrowDown' ? 1 : options.length - 1)) % options.length);

                return;
            }
            if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                choose(options[Math.min(active, options.length - 1)]);

                return;
            }
        }
        if (open && event.key === 'Escape') {
            // Closes the list, not the panel.
            event.preventDefault();
            setTrigger(null);

            return;
        }
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submit();
        }
    };

    return (
        <ComposerPrimitive.Root className="masilia-chat__composer">
            <div
                className={`masilia-chat__composer-card${dragging ? ' masilia-chat__composer-card--drop' : ''}`}
                onDragOver={(event) => {
                    if (event.dataTransfer.types.includes('Files')) {
                        event.preventDefault();
                        setDragging(true);
                    }
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(event) => {
                    if (event.dataTransfer.files.length > 0) {
                        event.preventDefault();
                        setDragging(false);
                        void addFiles([...event.dataTransfer.files]);
                    }
                }}
            >
                {open ? <MentionList id={LIST_ID} options={options} active={active} search={search} query={trigger.query} onChoose={choose} onHover={setActive} /> : null}
                <PageChip />
                {attachments.length > 0 || uploading > 0 ? (
                    <ul className="masilia-chat__attachments" aria-label="Attached documents">
                        {attachments.map((attachment, index) => (
                            <li key={`${attachment.name}-${index}`} className="masilia-chat__attachment">
                                {attachment.kind === 'image' && attachment.previewUrl !== undefined ? (
                                    <img className="masilia-chat__attachment-thumb" src={attachment.previewUrl} alt="" />
                                ) : (
                                    <Icon name="file-text" size="small" />
                                )}
                                <span className="masilia-chat__attachment-name">{attachment.name}</span>
                                <span className="masilia-chat__attachment-size">{formatBytes(attachment.bytes)}</span>
                                <button
                                    type="button"
                                    className="masilia-chat__attachment-remove"
                                    aria-label={`Remove ${attachment.name}`}
                                    title="Remove"
                                    onClick={() => {
                                        forget(attachment);
                                        setAttachments((previous) => previous.filter((_item, at) => at !== index));
                                    }}
                                >
                                    <Icon name="discard" size="small" />
                                </button>
                            </li>
                        ))}
                        {uploading > 0 ? <li className="masilia-chat__attachment masilia-chat__shimmer" role="status">Uploading…</li> : null}
                    </ul>
                ) : null}
                {attachError !== null ? <p className="masilia-chat__error" role="alert">{attachError}</p> : null}
                <label className="visually-hidden" htmlFor="masilia-chat-input">Message the assistant</label>
                <ComposerPrimitive.Input
                    ref={input}
                    id="masilia-chat-input"
                    className="masilia-chat__input"
                    rows={1}
                    maxRows={6}
                    disabled={disabled}
                    // Sending is done here, so that the mentions can be turned into their tokens first.
                    submitMode="none"
                    placeholder={disabled ? 'Approve or refuse the change above first' : 'Ask, or describe a change… (@ to mention a content)'}
                    role="combobox"
                    aria-expanded={open}
                    aria-controls={open ? LIST_ID : undefined}
                    aria-activedescendant={open && options.length > 0 ? `${LIST_ID}-${Math.min(active, options.length - 1)}` : undefined}
                    aria-autocomplete="list"
                    onKeyDown={onKeyDown}
                    onChange={(event) => refresh(event.currentTarget)}
                    onClick={(event) => refresh(event.currentTarget)}
                    onKeyUp={(event) => {
                        // Moving in the list is not moving in the text.
                        if (!['ArrowUp', 'ArrowDown', 'Enter', 'Tab', 'Escape'].includes(event.key)) {
                            refresh(event.currentTarget);
                        }
                    }}
                    onBlur={() => setTrigger(null)}
                />
                <div className="masilia-chat__composer-bar">
                    <input
                        ref={picker}
                        type="file"
                        multiple
                        hidden
                        accept=".txt,.md,.markdown,.csv,.tsv,.json,.html,.htm,.xml,.yaml,.yml,.srt,.log,.jpg,.jpeg,.png,.gif,.webp,text/*,image/jpeg,image/png,image/gif,image/webp"
                        onChange={(event) => {
                            void addFiles([...(event.currentTarget.files ?? [])]);
                            event.currentTarget.value = '';
                        }}
                    />
                    <button type="button" className="masilia-chat__icon-button" title="Attach an image or a text document" aria-label="Attach an image or a text document" disabled={disabled} onClick={() => picker.current?.click()}>
                        <Icon name="file-add" size="small" />
                    </button>
                    <span className="masilia-chat__hint">Enter to send · @ to mention</span>
                    {running ? (
                        <ComposerPrimitive.Cancel className="masilia-chat__send" title="Stop" aria-label="Stop">
                            <Icon name="discard" size="small" />
                        </ComposerPrimitive.Cancel>
                    ) : (
                        <button type="button" className="masilia-chat__send masilia-chat__send--primary" title="Send" aria-label="Send" disabled={disabled || uploading > 0 || (text.trim() === '' && attachments.length === 0)} onClick={submit}>
                            <Icon name="arrow-up" size="small" />
                        </button>
                    )}
                </div>
            </div>
            <p className="masilia-chat__disclaimer">AI can make mistakes. Review changes before you approve them.</p>
        </ComposerPrimitive.Root>
    );
}

/**
 * Tells the user which page the assistant knows they are on, and lets them stop sharing it.
 */
function PageChip() {
    const shares = useSharesPage();
    const page = useMemo(openPage, []);
    if (page === null) {
        return null;
    }

    return (
        <button
            type="button"
            className={`masilia-chat__chip${shares ? '' : ' masilia-chat__chip--off'}`}
            aria-pressed={shares}
            title={shares ? 'The assistant knows you are on this page. Click to stop sharing it.' : 'Click to let the assistant know which page you are on.'}
            onClick={() => setSharesPage(!shares)}
        >
            <Icon name="file-text" size="small" />
            <span className="masilia-chat__chip-text">{shares ? page.name : `${page.name} (not shared)`}</span>
            <Icon name={shares ? 'discard' : 'add'} size="small" />
        </button>
    );
}
