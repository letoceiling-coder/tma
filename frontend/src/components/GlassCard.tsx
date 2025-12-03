import { ReactNode, HTMLAttributes } from "react";
import { cn } from "@/lib/utils";

interface GlassCardProps extends HTMLAttributes<HTMLDivElement> {
  children: ReactNode;
  className?: string;
  glowColor?: "cyan" | "purple" | "gold";
}

const GlassCard = ({ children, className, glowColor, ...props }: GlassCardProps) => {
  const glowClass = glowColor
    ? glowColor === "cyan"
      ? "neon-glow-cyan"
      : glowColor === "purple"
      ? "neon-glow-purple"
      : "neon-glow-gold"
    : "";

  return (
    <div
      className={cn(
        "glass-card rounded-2xl p-6 transition-smooth",
        glowClass,
        className
      )}
      {...props}
    >
      {children}
    </div>
  );
};

export default GlassCard;
