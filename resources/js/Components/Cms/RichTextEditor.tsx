import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import LinkExt from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import {
  AlignCenter,
  AlignLeft,
  Bold,
  Heading2,
  Heading3,
  Italic,
  Link2,
  List,
  ListOrdered,
  Minus,
  Quote,
  Redo2,
  Underline as UnderlineIcon,
  Undo2,
  Unlink,
} from 'lucide-react';
import { useEffect } from 'react';
import { IconButton } from './ui';

/** TipTap (MIT) editor. Output is sanitized again on the server. */
export default function RichTextEditor({ value, onChange, id }: { value: string; onChange: (html: string) => void; id?: string }) {
  const editor = useEditor({
        immediatelyRender: false,
    extensions: [
      StarterKit.configure({ heading: { levels: [2, 3, 4] } }),
      Underline,
      LinkExt.configure({ openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer' } }),
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
    ],
    content: value || '',
    editorProps: { attributes: { class: 'cms-content', 'aria-label': 'Page content', ...(id ? { id } : {}) } },
    onUpdate: ({ editor: e }) => onChange(e.getHTML()),
  });

  useEffect(() => {
    if (editor && value !== editor.getHTML() && !editor.isFocused) {
      editor.commands.setContent(value || '', false);
    }
  }, [value, editor]);

  if (!editor) return null;

  const setLink = () => {
    const prev = editor.getAttributes('link').href as string | undefined;
    const url = window.prompt('Link URL (https://…, /page or mailto:)', prev || 'https://');
    if (url === null) return;
    if (url === '') {
      editor.chain().focus().unsetLink().run();
      return;
    }
    editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  };

  const btn = (label: string, icon: JSX.Element, run: () => void, active = false) => (
    <IconButton label={label} onClick={run} className={active ? 'bg-emerald-50 text-[#047857] dark:bg-emerald-950' : ''} aria-pressed={active}>
      {icon}
    </IconButton>
  );

  return (
    <div className="cms-editor overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
      <div role="toolbar" aria-label="Formatting" className="flex flex-wrap gap-0.5 border-b border-slate-200 bg-slate-50 p-1.5 dark:border-slate-700 dark:bg-slate-800/60">
        {btn('Bold', <Bold size={16} />, () => editor.chain().focus().toggleBold().run(), editor.isActive('bold'))}
        {btn('Italic', <Italic size={16} />, () => editor.chain().focus().toggleItalic().run(), editor.isActive('italic'))}
        {btn('Underline', <UnderlineIcon size={16} />, () => editor.chain().focus().toggleUnderline().run(), editor.isActive('underline'))}
        {btn('Heading', <Heading2 size={16} />, () => editor.chain().focus().toggleHeading({ level: 2 }).run(), editor.isActive('heading', { level: 2 }))}
        {btn('Subheading', <Heading3 size={16} />, () => editor.chain().focus().toggleHeading({ level: 3 }).run(), editor.isActive('heading', { level: 3 }))}
        {btn('Bullet list', <List size={16} />, () => editor.chain().focus().toggleBulletList().run(), editor.isActive('bulletList'))}
        {btn('Numbered list', <ListOrdered size={16} />, () => editor.chain().focus().toggleOrderedList().run(), editor.isActive('orderedList'))}
        {btn('Quote', <Quote size={16} />, () => editor.chain().focus().toggleBlockquote().run(), editor.isActive('blockquote'))}
        {btn('Align left', <AlignLeft size={16} />, () => editor.chain().focus().setTextAlign('left').run(), editor.isActive({ textAlign: 'left' }))}
        {btn('Align center', <AlignCenter size={16} />, () => editor.chain().focus().setTextAlign('center').run(), editor.isActive({ textAlign: 'center' }))}
        {btn('Add link', <Link2 size={16} />, setLink, editor.isActive('link'))}
        {btn('Remove link', <Unlink size={16} />, () => editor.chain().focus().unsetLink().run())}
        {btn('Divider', <Minus size={16} />, () => editor.chain().focus().setHorizontalRule().run())}
        {btn('Undo', <Undo2 size={16} />, () => editor.chain().focus().undo().run())}
        {btn('Redo', <Redo2 size={16} />, () => editor.chain().focus().redo().run())}
      </div>
      <EditorContent editor={editor} />
    </div>
  );
}
