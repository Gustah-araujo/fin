import { useState, useEffect } from 'react'
import { HexColorPicker } from 'react-colorful'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'

interface ColorPickerProps {
  value?: string
  onChange?: (color: string) => void
  className?: string
}

export function ColorPicker({ value, onChange, className }: ColorPickerProps) {
  const [isOpen, setIsOpen] = useState(false)
  const [color, setColor] = useState(value || '#000000')

  useEffect(() => {
    if (value) {
      setColor(value)
    }
  }, [value])

  function handlePickerChange(newColor: string) {
    setColor(newColor)
    onChange?.(newColor)
  }

  function handleInputChange(e: React.ChangeEvent<HTMLInputElement>) {
    const input = e.target.value
    setColor(input)
    if (/^#[0-9A-Fa-f]{6}$/.test(input)) {
      onChange?.(input.toUpperCase())
    }
  }

  function handleInputBlur() {
    const normalized = normalizeColor(color)
    setColor(normalized)
    onChange?.(normalized)
  }

  function normalizeColor(c: string): string {
    let normalized = c.trim()
    if (normalized.length === 6 && /^[0-9A-Fa-f]{6}$/.test(normalized)) {
      normalized = '#' + normalized
    }
    if (/^#[0-9A-Fa-f]{6}$/.test(normalized)) {
      return normalized.toUpperCase()
    }
    return '#000000'
  }

  return (
    <div className={cn('flex items-center gap-3', className)}>
      <Popover open={isOpen} onOpenChange={setIsOpen}>
        <PopoverTrigger asChild>
          <Button
            variant="outline"
            size="icon"
            className={cn(
              'size-7 rounded-full border-2',
              !value && 'border-dashed border-muted-foreground/40'
            )}
            style={{ backgroundColor: value || 'transparent' }}
            aria-label="Pick a color"
          />
        </PopoverTrigger>
        <PopoverContent className="w-auto p-3">
          <HexColorPicker color={color} onChange={handlePickerChange} />
        </PopoverContent>
      </Popover>
      <Input
        value={color}
        onChange={handleInputChange}
        onBlur={handleInputBlur}
        placeholder="#000000"
        className="h-8 w-28 font-mono text-xs"
      />
    </div>
  )
}
