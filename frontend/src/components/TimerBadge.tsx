import { useEffect, useState } from "react";

interface TimerBadgeProps {
  targetTime: Date;
}

const TimerBadge = ({ targetTime }: TimerBadgeProps) => {
  const [timeLeft, setTimeLeft] = useState("");

  useEffect(() => {
    const updateTimer = () => {
      const now = new Date().getTime();
      const target = targetTime.getTime();
      const difference = target - now;

      if (difference <= 0) {
        setTimeLeft("00:00:00");
        return;
      }

      const hours = Math.floor(difference / (1000 * 60 * 60));
      const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((difference % (1000 * 60)) / 1000);

      setTimeLeft(
        `${hours.toString().padStart(2, "0")}:${minutes
          .toString()
          .padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`
      );
    };

    updateTimer();
    const interval = setInterval(updateTimer, 1000);

    return () => clearInterval(interval);
  }, [targetTime]);

  return (
    <div className="inline-flex items-center gap-2 px-6 py-2 rounded-2xl" style={{
      background: 'rgba(255, 255, 255, 0.9)',
      boxShadow: '0 2px 8px rgba(0,0,0,0.1)'
    }}>
      <span className="text-xl font-bold tabular-nums tracking-wide" style={{
        color: 'hsl(0 0% 30%)'
      }}>
        {timeLeft}
      </span>
    </div>
  );
};

export default TimerBadge;
