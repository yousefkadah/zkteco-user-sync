import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    "inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md text-[13px] font-medium transition-[color,background-color,border-color,transform] active:scale-[0.98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-inset focus-visible:ring-[var(--ring)] disabled:pointer-events-none disabled:opacity-50 disabled:active:scale-100 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0",
    {
        variants: {
            variant: {
                default:
                    'bg-accent-brand text-accent-brand-fg hover:bg-[var(--accent-brand-hover)] active:bg-[var(--accent-brand-active)]',
                destructive:
                    'bg-danger text-danger-foreground hover:brightness-110',
                outline: 'border border-border bg-transparent hover:bg-accent hover:text-accent-foreground',
                secondary: 'border border-border bg-muted hover:bg-accent',
                ghost: 'hover:bg-accent hover:text-accent-foreground',
                link: 'text-accent-brand underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-[var(--ctl-h)] px-3',
                sm: 'h-6 px-2 text-[12px]',
                lg: 'h-8 px-3.5',
                icon: 'size-7',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    asChild?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, asChild = false, ...props }, ref) => {
        const Comp = asChild ? Slot : 'button';
        return <Comp className={cn(buttonVariants({ variant, size, className }))} ref={ref} {...props} />;
    },
);
Button.displayName = 'Button';

export { Button, buttonVariants };
