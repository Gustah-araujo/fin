"use client"

import * as React from "react"
import { Check, ChevronDown } from "lucide-react"

import { cn } from "@/lib/utils"
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from "@/components/ui/command"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"

interface SelectContextValue {
  value: string
  onSelect: (value: string) => void
  disabled: boolean
  filter: boolean
  onSearch?: (query: string) => void
  loading: boolean
  emptyMessage: string
  labels: Record<string, string>
  registerItem: (value: string, label: string) => void
}

const SelectContext = React.createContext<SelectContextValue | null>(null)

function useSelectContext() {
  const ctx = React.useContext(SelectContext)
  if (!ctx) {
    throw new Error("Select components must be used within a <Select>")
  }
  return ctx
}

interface SelectProps {
  value?: string
  defaultValue?: string
  onValueChange?: (value: string) => void
  disabled?: boolean
  filter?: boolean
  onSearch?: (query: string) => void
  loading?: boolean
  emptyMessage?: string
  children: React.ReactNode
}

function Select({
  value,
  defaultValue,
  onValueChange,
  disabled = false,
  filter = false,
  onSearch,
  loading = false,
  emptyMessage = "Nenhum resultado.",
  children,
}: SelectProps) {
  const [internalValue, setInternalValue] = React.useState(defaultValue ?? "")
  const [labels, setLabels] = React.useState<Record<string, string>>({})
  const [open, setOpen] = React.useState(false)

  const selected = value !== undefined ? value : internalValue

  const onSelect = React.useCallback(
    (next: string) => {
      if (value === undefined) {
        setInternalValue(next)
      }
      onValueChange?.(next)
      setOpen(false)
    },
    [value, onValueChange]
  )

  const registerItem = React.useCallback((itemValue: string, label: string) => {
    setLabels((prev) =>
      prev[itemValue] === label ? prev : { ...prev, [itemValue]: label }
    )
  }, [])

  const ctx = React.useMemo<SelectContextValue>(
    () => ({
      value: selected,
      onSelect,
      disabled,
      filter,
      onSearch,
      loading,
      emptyMessage,
      labels,
      registerItem,
    }),
    [selected, onSelect, disabled, filter, onSearch, loading, emptyMessage, labels, registerItem]
  )

  return (
    <SelectContext.Provider value={ctx}>
      <Popover open={open} onOpenChange={setOpen}>
        {children}
      </Popover>
    </SelectContext.Provider>
  )
}

function SelectTrigger({
  className,
  children,
  ...props
}: React.ComponentProps<"button">) {
  const ctx = useSelectContext()

  return (
    <PopoverTrigger asChild>
      <button
        type="button"
        role="combobox"
        data-slot="select-trigger"
        disabled={ctx.disabled}
        className={cn(
          "flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-hidden transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&>span]:line-clamp-1",
          className
        )}
        {...props}
      >
        {children}
        <ChevronDown className="size-4 shrink-0 opacity-50" />
      </button>
    </PopoverTrigger>
  )
}

interface SelectValueProps {
  placeholder?: string
  className?: string
}

function SelectValue({ placeholder, className }: SelectValueProps) {
  const ctx = useSelectContext()
  const label = ctx.labels[ctx.value]

  return (
    <span
      className={cn(
        "block truncate",
        !label && "text-muted-foreground",
        className
      )}
    >
      {label ?? placeholder ?? ""}
    </span>
  )
}

function SelectContent({
  className,
  children,
  align = "start",
  sideOffset = 4,
  ...props
}: React.ComponentProps<typeof PopoverContent>) {
  const ctx = useSelectContext()
  const showInput = ctx.filter
  const shouldFilter = Boolean(ctx.filter && !ctx.onSearch)

  return (
    <PopoverContent
      align={align}
      sideOffset={sideOffset}
      className={cn("w-[var(--radix-popover-trigger-width)] p-0", className)}
      {...props}
    >
      <Command shouldFilter={shouldFilter}>
        {showInput && (
          <CommandInput placeholder="Buscar..." onValueChange={ctx.onSearch} />
        )}
        <CommandList>
          <CommandEmpty>
            {ctx.loading ? "Carregando..." : ctx.emptyMessage}
          </CommandEmpty>
          {children}
        </CommandList>
      </Command>
    </PopoverContent>
  )
}

interface SelectItemProps extends React.ComponentProps<typeof CommandItem> {
  value: string
  children?: React.ReactNode
  className?: string
  disabled?: boolean
  searchValue?: string
}

function SelectItem({
  value,
  children,
  className,
  disabled,
  searchValue,
  ...props
}: SelectItemProps) {
  const ctx = useSelectContext()

  const label =
    typeof children === "string" ? children : searchValue ?? value
  const isSelected = ctx.value === value

  React.useEffect(() => {
    ctx.registerItem(value, label)
  }, [value, label, ctx])

  return (
    <CommandItem
      value={searchValue ?? label}
      disabled={disabled}
      onSelect={() => ctx.onSelect(value)}
      className={cn("relative", className)}
      {...props}
    >
      <span className="block truncate">{children}</span>
      {isSelected && <Check className="ml-auto size-4" />}
    </CommandItem>
  )
}

function SelectGroup(props: React.ComponentProps<typeof CommandGroup>) {
  return <CommandGroup {...props} />
}

function SelectLabel({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div className={cn("px-2 py-1.5 text-sm font-semibold", className)} {...props} />
  )
}

function SelectSeparator(props: React.ComponentProps<typeof CommandSeparator>) {
  return <CommandSeparator {...props} />
}

export {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectSeparator,
  SelectTrigger,
  SelectValue,
}
