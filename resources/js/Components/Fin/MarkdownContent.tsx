import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import type { ComponentPropsWithoutRef } from 'react';

interface Props {
    content: string;
}

type HeadingProps = ComponentPropsWithoutRef<'h1'> & { node?: unknown };

export default function MarkdownContent({ content }: Props) {
    return (
        <div className="min-w-0 text-sm leading-relaxed text-foreground">
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={{
                    h1: ({ node: _node, ...props }: HeadingProps) => (
                        <h1
                            className="mb-2 mt-4 text-lg font-semibold text-foreground"
                            {...props}
                        />
                    ),
                    h2: ({ node: _node, ...props }: HeadingProps) => (
                        <h2
                            className="mb-2 mt-4 text-base font-semibold text-foreground"
                            {...props}
                        />
                    ),
                    h3: ({ node: _node, ...props }: HeadingProps) => (
                        <h3
                            className="mb-1 mt-3 text-sm font-semibold text-foreground"
                            {...props}
                        />
                    ),
                    p: (props) => (
                        <p className="my-1.5 leading-relaxed" {...props} />
                    ),
                    strong: (props) => (
                        <strong className="font-semibold" {...props} />
                    ),
                    em: (props) => <em className="italic" {...props} />,
                    ul: (props) => (
                        <ul
                            className="my-1.5 list-disc space-y-1 pl-5"
                            {...props}
                        />
                    ),
                    ol: (props) => (
                        <ol
                            className="my-1.5 list-decimal space-y-1 pl-5"
                            {...props}
                        />
                    ),
                    li: (props) => (
                        <li className="leading-relaxed" {...props} />
                    ),
                    code: (props) => (
                        <code
                            className="rounded bg-muted-foreground/10 px-1.5 py-0.5 font-mono text-xs"
                            {...props}
                        />
                    ),
                    pre: (props) => (
                        <pre
                            className="my-2 overflow-x-auto rounded-lg bg-muted-foreground/10 p-3 font-mono text-xs"
                            {...props}
                        />
                    ),
                    blockquote: (props) => (
                        <blockquote
                            className="my-2 border-l-2 border-border pl-3 italic text-muted-foreground"
                            {...props}
                        />
                    ),
                    table: (props) => (
                        <table
                            className="my-2 w-full border-collapse text-sm"
                            {...props}
                        />
                    ),
                    thead: (props) => (
                        <thead className="border-b border-border" {...props} />
                    ),
                    th: (props) => (
                        <th
                            className="border-b border-border px-2 py-1 text-left font-semibold"
                            {...props}
                        />
                    ),
                    td: (props) => (
                        <td
                            className="border-b border-border/50 px-2 py-1 align-top"
                            {...props}
                        />
                    ),
                    a: (props) => (
                        <a
                            className="text-primary underline hover:opacity-80"
                            {...props}
                        />
                    ),
                    hr: (props) => (
                        <hr className="my-2 border-border" {...props} />
                    ),
                }}
            >
                {content}
            </ReactMarkdown>
        </div>
    );
}
