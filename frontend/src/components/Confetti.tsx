import { useEffect, useState } from "react";

interface ConfettiPiece {
  id: number;
  x: number;
  color: string;
  delay: number;
  duration: number;
  size: number;
  rotation: number;
}

interface ConfettiProps {
  isActive: boolean;
}

const Confetti = ({ isActive }: ConfettiProps) => {
  const [pieces, setPieces] = useState<ConfettiPiece[]>([]);

  const colors = [
    '#FFD700', // Gold
    '#FF6B6B', // Coral
    '#4ECDC4', // Teal
    '#FFE66D', // Yellow
    '#FF8C42', // Orange
    '#A855F7', // Purple
    '#EC4899', // Pink
    '#22C55E', // Green
  ];

  useEffect(() => {
    if (isActive) {
      const newPieces: ConfettiPiece[] = [];
      for (let i = 0; i < 50; i++) {
        newPieces.push({
          id: i,
          x: Math.random() * 100,
          color: colors[Math.floor(Math.random() * colors.length)],
          delay: Math.random() * 0.5,
          duration: 2 + Math.random() * 2,
          size: 8 + Math.random() * 8,
          rotation: Math.random() * 360,
        });
      }
      setPieces(newPieces);
    } else {
      setPieces([]);
    }
  }, [isActive]);

  if (!isActive || pieces.length === 0) return null;

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        pointerEvents: 'none',
        zIndex: 200,
        overflow: 'hidden',
      }}
    >
      {pieces.map((piece) => (
        <div
          key={piece.id}
          style={{
            position: 'absolute',
            left: `${piece.x}%`,
            top: '-20px',
            width: `${piece.size}px`,
            height: `${piece.size}px`,
            backgroundColor: piece.color,
            borderRadius: piece.id % 3 === 0 ? '50%' : piece.id % 3 === 1 ? '2px' : '0',
            transform: `rotate(${piece.rotation}deg)`,
            animation: `confetti-fall ${piece.duration}s cubic-bezier(0.25, 0.46, 0.45, 0.94) ${piece.delay}s forwards`,
            boxShadow: `0 2px 4px rgba(0,0,0,0.2)`,
          }}
        />
      ))}
      <style>
        {`
          @keyframes confetti-fall {
            0% {
              transform: translateY(0) rotate(0deg) scale(1);
              opacity: 1;
            }
            25% {
              transform: translateY(25vh) rotate(180deg) scale(1.1) translateX(20px);
            }
            50% {
              transform: translateY(50vh) rotate(360deg) scale(0.9) translateX(-20px);
            }
            75% {
              transform: translateY(75vh) rotate(540deg) scale(1) translateX(10px);
            }
            100% {
              transform: translateY(110vh) rotate(720deg) scale(0.8) translateX(-10px);
              opacity: 0;
            }
          }
        `}
      </style>
    </div>
  );
};

export default Confetti;