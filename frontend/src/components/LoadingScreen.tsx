import { useEffect, useState } from "react";
import loadingBunny from "@/assets/loading-bunny.png";

interface LoadingScreenProps {
  onComplete: () => void;
}

const LoadingScreen = ({ onComplete }: LoadingScreenProps) => {
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    const duration = 1500; // 1.5 seconds
    const interval = 30;
    const increment = 100 / (duration / interval);

    const timer = setInterval(() => {
      setProgress((prev) => {
        if (prev >= 100) {
          clearInterval(timer);
          setTimeout(onComplete, 200);
          return 100;
        }
        return Math.min(prev + increment, 100);
      });
    }, interval);

    return () => clearInterval(timer);
  }, [onComplete]);

  return (
    <div 
      className="fixed inset-0 z-50 flex flex-col items-center justify-center"
      style={{ 
        background: '#FECFB2',
        fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif"
      }}
      role="progressbar"
      aria-label="Загрузка приложения. Подключаем игру..."
      aria-valuenow={progress}
      aria-valuemin={0}
      aria-valuemax={100}
    >
      {/* Main bunny image */}
      <div 
        style={{
          flex: 1,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '24px',
          paddingBottom: '0'
        }}
      >
        <img 
          src={loadingBunny}
          alt="Белый кролик держит пушистую надпись 'WOW РУЛЕТКА'"
          style={{
            width: '80vw',
            maxWidth: '320px',
            height: 'auto',
            objectFit: 'contain'
          }}
        />
      </div>

      {/* Loading section at bottom */}
      <div
        style={{
          width: '100%',
          padding: '24px',
          paddingBottom: '60px',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          gap: '12px'
        }}
      >
        {/* Progress bar track */}
        <div
          style={{
            width: '70%',
            maxWidth: '280px',
            height: '10px',
            borderRadius: '12px',
            background: '#FFE0C5',
            overflow: 'hidden'
          }}
        >
          {/* Progress bar fill */}
          <div
            style={{
              width: `${progress}%`,
              height: '100%',
              borderRadius: '12px',
              background: '#E98A65',
              transition: 'width 0.1s linear'
            }}
          />
        </div>

        {/* Loading text */}
        <p
          style={{
            fontSize: '16px',
            fontWeight: 600,
            color: '#CC5C47',
            textAlign: 'center',
            letterSpacing: '0.5px',
            margin: 0,
            textTransform: 'uppercase'
          }}
        >
          ПОДКЛЮЧАЕМ ИГРУ...
        </p>
      </div>
    </div>
  );
};

export default LoadingScreen;