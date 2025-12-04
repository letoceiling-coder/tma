import { useState, useEffect } from "react";
import prize300 from "@/assets/wheel/prize-300.png";
import prize500 from "@/assets/wheel/prize-500.png";
import prize0 from "@/assets/wheel/prize-0.png";
import prizeTicket from "@/assets/wheel/prize-ticket.png";
import prizeSecret from "@/assets/wheel/prize-secret.png";
import prizeWow from "@/assets/wheel/prize-wow.png";

interface WheelSegment {
  value: number;
  text: string;
  prizeType?: string;
  iconUrl?: string | null;
}

interface WheelComponentProps {
  segments: WheelSegment[];
  onSpinComplete?: (winningIndex: number) => void;
  rotation: number;
}

// Функция для получения иконки по типу приза (fallback)
const getFallbackIcon = (prizeType?: string, value?: number): string => {
  if (!prizeType) return prize0;
  
  switch (prizeType) {
    case 'money':
      if (value === 300) return prize300;
      if (value === 500) return prize500;
      if (value === 1000) return prize500; // Можно использовать другую иконку для 1000
      if (value === 2000) return prizeWow;
      return prize0;
    case 'ticket':
      return prizeTicket;
    case 'secret_box':
      return prizeSecret;
    default:
      return prize0;
  }
};

// Функция для получения полного URL иконки
const getIconUrl = (iconUrl: string | null | undefined, prizeType?: string, value?: number): string => {
  if (iconUrl) {
    // Если URL начинается с /, добавляем базовый путь
    if (iconUrl.startsWith('/')) {
      return iconUrl;
    }
    // Если это полный URL, возвращаем как есть
    if (iconUrl.startsWith('http')) {
      return iconUrl;
    }
    // Иначе предполагаем, что это относительный путь
    return `/${iconUrl}`;
  }
  // Fallback на иконки по типу приза
  return getFallbackIcon(prizeType, value);
};

// Alternating sector colors - peach tones
const sectorColors = [
  "#FFE5D9", // light peach
  "#FFD4C2", // slightly darker peach
];

const WheelComponent = ({ segments, rotation, onSpinComplete }: WheelComponentProps) => {
  const [currentRotation, setCurrentRotation] = useState(0);
  const [isAnimating, setIsAnimating] = useState(false);
  const segmentAngle = 360 / segments.length;

  useEffect(() => {
    if (rotation !== currentRotation && rotation > currentRotation) {
      setIsAnimating(true);
      
      // Use requestAnimationFrame for smoother animation start
      requestAnimationFrame(() => {
        setCurrentRotation(rotation);
      });

      const timer = setTimeout(() => {
        setIsAnimating(false);
        const normalizedRotation = rotation % 360;
        const winningIndex = Math.floor((360 - normalizedRotation + segmentAngle / 2) / segmentAngle) % segments.length;
        
        // Отладочная информация
        console.log('=== WHEEL STOPPED ===');
        console.log('Total rotation:', rotation);
        console.log('Normalized rotation:', normalizedRotation);
        console.log('Calculated winningIndex:', winningIndex);
        console.log('Winning segment:', segments[winningIndex]);
        console.log('Sector number:', winningIndex + 1); // sector_number = index + 1
        console.log('==================');
        
        onSpinComplete?.(winningIndex);
      }, 4000);

      return () => clearTimeout(timer);
    }
  }, [rotation, currentRotation, segmentAngle, segments.length, onSpinComplete]);

  const wheelSize = 350;
  const centerX = 175; // wheelSize / 2
  const centerY = 175;
  const radius = 165; // outer radius for sectors
  const centerCircleRadius = 30; // center circle size
  const prizeDistance = 115; // distance of prizes from center

  // Generate path for a triangular sector
  const createSectorPath = (index: number) => {
    const startAngle = (index * 30 - 90) * (Math.PI / 180); // Start from top (-90°)
    const endAngle = ((index + 1) * 30 - 90) * (Math.PI / 180);
    
    const x1 = centerX + radius * Math.cos(startAngle);
    const y1 = centerY + radius * Math.sin(startAngle);
    const x2 = centerX + radius * Math.cos(endAngle);
    const y2 = centerY + radius * Math.sin(endAngle);
    
    return `M ${centerX} ${centerY} L ${x1} ${y1} A ${radius} ${radius} 0 0 1 ${x2} ${y2} Z`;
  };

  return (
    <div 
      className="wheel-wrapper relative mx-auto"
      style={{
        width: `${wheelSize + 50}px`,
        height: `${wheelSize + 80}px`,
        paddingTop: '50px',
      }}
    >
      {/* Soft shadow underlay - fixed, doesn't rotate */}
      <div
        className="absolute"
        style={{
          left: '25px',
          top: '50px',
          width: `${wheelSize}px`,
          height: `${wheelSize}px`,
          background: 'radial-gradient(circle, rgba(255, 255, 255, 0.4) 0%, rgba(255, 255, 255, 0) 70%)',
          transform: 'translateY(8px)',
          filter: 'blur(12px)',
          zIndex: 0,
        }}
      />

      {/* Top pointer (SVG) - fixed, doesn't rotate */}
      <svg
        className="absolute pointer-events-none"
        width="52"
        height="62"
        viewBox="0 0 52 62"
        style={{
          left: '50%',
          top: '18px',
          transform: 'translateX(-50%)',
          zIndex: 11,
          filter: 'drop-shadow(0 4px 10px rgba(232, 157, 114, 0.5))',
        }}
      >
        <defs>
          <linearGradient id="pointerGradient" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stopColor="#FFD4C2" />
            <stop offset="50%" stopColor="#FFBEA8" />
            <stop offset="100%" stopColor="#F6A58C" />
          </linearGradient>
          <radialGradient id="pointerInnerGlow" cx="50%" cy="30%" r="50%">
            <stop offset="0%" stopColor="rgba(255, 255, 255, 0.5)" />
            <stop offset="100%" stopColor="rgba(255, 255, 255, 0)" />
          </radialGradient>
          <filter id="pointerShadow" x="-50%" y="-50%" width="200%" height="200%">
            <feGaussianBlur in="SourceAlpha" stdDeviation="2.5"/>
            <feOffset dx="0" dy="3" result="offsetblur"/>
            <feComponentTransfer>
              <feFuncA type="linear" slope="0.35"/>
            </feComponentTransfer>
            <feMerge>
              <feMergeNode/>
              <feMergeNode in="SourceGraphic"/>
            </feMerge>
          </filter>
        </defs>
        
        {/* Inner shadow for depth */}
        <path
          d="M 26 4 
             C 16 4, 10 10, 10 19
             C 10 27, 16 34, 26 58
             C 36 34, 42 27, 42 19
             C 42 10, 36 4, 26 4 Z"
          fill="rgba(0, 0, 0, 0.12)"
          opacity="0.5"
        />
        
        {/* Secondary inner shadow for more depth */}
        <path
          d="M 26 6 
             C 17 6, 12 11, 12 19
             C 12 26, 17 32, 26 54
             C 35 32, 40 26, 40 19
             C 40 11, 35 6, 26 6 Z"
          fill="rgba(0, 0, 0, 0.06)"
          opacity="0.6"
        />
        
        {/* Main pointer shape - droplet/heart pointing down */}
        <path
          d="M 26 4 
             C 16 4, 10 10, 10 19
             C 10 27, 16 34, 26 58
             C 36 34, 42 27, 42 19
             C 42 10, 36 4, 26 4 Z"
          fill="url(#pointerGradient)"
          stroke="#FFFFFF"
          strokeWidth="3"
          filter="url(#pointerShadow)"
        />
        
        {/* Top highlight/shine for glossy effect */}
        <ellipse
          cx="26"
          cy="14"
          rx="8"
          ry="5"
          fill="rgba(255, 255, 255, 0.4)"
          opacity="0.8"
        />
        
        {/* Inner glow for 3D volumetric effect */}
        <ellipse
          cx="26"
          cy="18"
          rx="10"
          ry="7"
          fill="url(#pointerInnerGlow)"
          opacity="0.6"
        />
      </svg>
      {/* 3D border ring - fixed, doesn't rotate */}
      <svg
        className="absolute pointer-events-none"
        width={wheelSize}
        height={wheelSize}
        viewBox={`0 0 ${wheelSize} ${wheelSize}`}
        style={{ 
          zIndex: 9,
          left: '25px',
          top: '50px',
        }}
      >
        <defs>
          <linearGradient id="borderGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stopColor="#FFD4A8" />
            <stop offset="30%" stopColor="#FFBE89" />
            <stop offset="60%" stopColor="#F6A974" />
            <stop offset="100%" stopColor="#E89D72" />
          </linearGradient>
          <filter id="border3D" x="-50%" y="-50%" width="200%" height="200%">
            <feGaussianBlur in="SourceAlpha" stdDeviation="2"/>
            <feOffset dx="0" dy="3" result="offsetblur"/>
            <feComponentTransfer>
              <feFuncA type="linear" slope="0.4"/>
            </feComponentTransfer>
            <feMerge>
              <feMergeNode/>
              <feMergeNode in="SourceGraphic"/>
            </feMerge>
          </filter>
        </defs>
        {/* Outer soft shadow */}
        <circle
          cx={centerX}
          cy={centerY}
          r={radius + 6}
          fill="none"
          stroke="rgba(232, 157, 114, 0.25)"
          strokeWidth="3"
          style={{
            filter: 'blur(4px)',
          }}
        />
        {/* Main border ring */}
        <circle
          cx={centerX}
          cy={centerY}
          r={radius + 3.5}
          fill="none"
          stroke="url(#borderGradient)"
          strokeWidth="7"
          filter="url(#border3D)"
        />
        {/* Inner highlight ring */}
        <circle
          cx={centerX}
          cy={centerY}
          r={radius + 1.5}
          fill="none"
          stroke="#FFEAD9"
          strokeWidth="2"
          opacity="0.6"
        />
        {/* Top highlight arc for 3D effect */}
        <path
          d={`M ${centerX - radius - 2} ${centerY} A ${radius + 2} ${radius + 2} 0 0 1 ${centerX + radius + 2} ${centerY}`}
          fill="none"
          stroke="rgba(255, 255, 255, 0.35)"
          strokeWidth="2.5"
          opacity="0.5"
        />
      </svg>

      {/* Rotating wheel container */}
      <div
        id="wheel"
        className="wheel-rotatable absolute"
        style={{
          left: '25px',
          top: '50px',
          width: `${wheelSize}px`,
          height: `${wheelSize}px`,
          transform: `rotate(${currentRotation}deg)`,
          transition: isAnimating ? 'transform 4s cubic-bezier(0.2, 0.8, 0.2, 1)' : 'none',
          transformOrigin: 'center center',
          zIndex: 2,
        }}
      >
        {/* SVG wheel with 12 triangular sectors */}
        <svg
          width={wheelSize}
          height={wheelSize}
          viewBox={`0 0 ${wheelSize} ${wheelSize}`}
          className="absolute inset-0"
        >
          {/* Draw 12 sectors */}
          {Array.from({ length: 12 }).map((_, index) => (
            <path
              key={`sector-${index}`}
              d={createSectorPath(index)}
              fill={sectorColors[index % 2]}
              stroke="#F6A974"
              strokeWidth="1"
              style={{
                filter: 'drop-shadow(0 2px 4px rgba(0,0,0,0.1))',
              }}
            />
          ))}
          
          {/* Center circle */}
          <circle
            cx={centerX}
            cy={centerY}
            r={centerCircleRadius}
            fill="url(#centerGradient)"
            stroke="#E89D72"
            strokeWidth="2"
            style={{
              filter: 'drop-shadow(0 3px 6px rgba(0,0,0,0.15))',
            }}
          />
          
          {/* Gradient definition for center circle */}
          <defs>
            <radialGradient id="centerGradient" cx="50%" cy="50%" r="50%">
              <stop offset="0%" stopColor="#FFECD9" />
              <stop offset="100%" stopColor="#FFD4C2" />
            </radialGradient>
          </defs>
        </svg>
        
        {/* Prize icons positioned in sectors */}
        {segments.map((segment, index) => {
          const sectorAngle = index * 30 + 15; // center of each 30° sector
          const angleRad = ((sectorAngle - 90) * Math.PI) / 180; // -90 to start from top
          const x = centerX + prizeDistance * Math.cos(angleRad);
          const y = centerY + prizeDistance * Math.sin(angleRad);
          
          // Используем iconUrl из базы данных, если есть, иначе fallback
          const iconSrc = getIconUrl(segment.iconUrl, segment.prizeType, segment.value);
          
          const iconSize = segment.prizeType === 'secret_box' ? 40 : 45;
          
          return (
            <img
              key={index}
              src={iconSrc}
              alt={segment.text || `Prize ${index + 1}`}
              className="prize absolute"
              style={{
                width: `${iconSize}px`,
                height: 'auto',
                left: `${x}px`,
                top: `${y}px`,
                transform: `translate(-50%, -50%) rotate(${sectorAngle}deg)`,
                transformOrigin: 'center center',
                zIndex: 4,
                pointerEvents: 'none',
              }}
              draggable={false}
              onError={(e) => {
                // Если загрузка иконки из базы данных не удалась, используем fallback
                const fallbackSrc = getFallbackIcon(segment.prizeType, segment.value);
                if (e.currentTarget.src !== fallbackSrc) {
                  e.currentTarget.src = fallbackSrc;
                }
              }}
            />
          );
        })}
      </div>
    </div>
  );
};

export default WheelComponent;
