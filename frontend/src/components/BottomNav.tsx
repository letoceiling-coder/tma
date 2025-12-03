import { NavLink } from "react-router-dom";
import friendsIcon from "@/assets/icons/friends-icon.png";
import topIcon from "@/assets/icons/top-icon.png";
import { haptic } from "@/lib/haptic";

const BottomNav = () => {
  const WheelIcon = ({ active }: { active: boolean }) => (
    <svg width="24" height="22" viewBox="0 0 31 29" fill="none" style={{ transition: 'all 0.25s ease' }}>
      <path 
        d="M27.701 20.9306C25.8034 23.9824 22.678 26.2092 19.0123 27.1212M27.701 20.9306C29.5985 17.8788 30.1127 14.2521 29.1305 10.8482M27.701 20.9306L18.0628 15.7633M19.0123 27.1212C15.3466 28.0332 11.4409 27.5557 8.15432 25.7938M19.0123 27.1212L16.132 17.139M8.15432 25.7938C4.86783 24.0317 2.46974 21.1296 1.48757 17.7257M8.15432 25.7938L13.7191 16.844M29.1305 10.8482C28.1483 7.44437 25.7502 4.5422 22.4638 2.78014M29.1305 10.8482L18.3805 13.5228M22.4638 2.78014C19.1772 1.01821 15.2715 0.540731 11.6058 1.45274M22.4638 2.78014L16.899 11.7299M18.0628 15.7633C17.6411 16.4415 16.9466 16.9363 16.132 17.139M18.0628 15.7633C18.4845 15.0852 18.5987 14.2792 18.3805 13.5228M1.48757 17.7257C0.505403 14.3219 1.01961 10.6951 2.91707 7.6433M1.48757 17.7257L12.2376 15.0511M2.91707 7.6433C4.81468 4.59156 7.94009 2.36476 11.6058 1.45274M2.91707 7.6433L12.5553 12.8106M13.7191 16.844C14.4495 17.2356 15.3174 17.3417 16.132 17.139M13.7191 16.844C12.9888 16.4525 12.4559 15.8075 12.2376 15.0511M11.6058 1.45274L14.4861 11.4349M12.5553 12.8106C12.1336 13.4888 12.0193 14.2947 12.2376 15.0511M12.5553 12.8106C12.977 12.1324 13.6715 11.6376 14.4861 11.4349M16.899 11.7299C17.6293 12.1215 18.1622 12.7664 18.3805 13.5228M16.899 11.7299C16.1686 11.3384 15.3007 11.2322 14.4861 11.4349" 
        stroke={active ? "#FFD489" : "#FFFFFF"} 
        strokeWidth="2" 
        strokeLinecap="round" 
        strokeLinejoin="round"
        style={{ transition: 'stroke 0.25s ease' }}
      />
    </svg>
  );

  const navItems = [
    { to: "/", label: "Колесо", icon: "wheel" },
    { to: "/friends", label: "Друзья", icon: "friends" },
    { to: "/leaderboard", label: "Топ", icon: "top" },
  ];

  return (
    <nav 
      className="fixed left-0 right-0 flex items-center justify-around"
      style={{
        bottom: 0,
        height: 'calc(60px + env(safe-area-inset-bottom, 0px))',
        minHeight: '60px',
        background: 'linear-gradient(180deg, #E07C63 0%, #D97059 100%)',
        borderRadius: '16px 16px 0 0',
        paddingBottom: 'env(safe-area-inset-bottom, 0px)',
        boxShadow: '0 -4px 12px rgba(0,0,0,0.08)',
        zIndex: 50
      }}
    >
      {navItems.map(({ to, label, icon }) => (
        <NavLink
          key={to}
          to={to}
          end
          onClick={() => haptic.selection()}
          className="flex flex-col items-center justify-center gap-1"
          style={{ 
            minWidth: '70px',
            padding: '8px 12px',
            borderRadius: '12px',
            transition: 'all 0.25s cubic-bezier(0.4, 0, 0.2, 1)',
            WebkitTapHighlightColor: 'transparent'
          }}
        >
          {({ isActive }) => (
            <div 
              className="flex flex-col items-center gap-1"
              style={{
                transform: isActive ? 'scale(1.05)' : 'scale(1)',
                transition: 'transform 0.25s cubic-bezier(0.4, 0, 0.2, 1)'
              }}
            >
              <div style={{ 
                height: '24px', 
                display: 'flex', 
                alignItems: 'center', 
                justifyContent: 'center',
                transition: 'all 0.25s ease'
              }}>
                {icon === "wheel" && <WheelIcon active={isActive} />}
                {icon === "friends" && (
                  <img 
                    src={friendsIcon} 
                    alt="Друзья" 
                    style={{ 
                      width: '22px', 
                      height: '22px',
                      filter: isActive 
                        ? 'brightness(0) saturate(100%) invert(85%) sepia(35%) saturate(700%) hue-rotate(340deg)' 
                        : 'brightness(0) invert(1)',
                      transition: 'filter 0.25s ease'
                    }} 
                  />
                )}
                {icon === "top" && (
                  <img 
                    src={topIcon} 
                    alt="Топ" 
                    style={{ 
                      width: '22px', 
                      height: '22px',
                      filter: isActive 
                        ? 'brightness(0) saturate(100%) invert(85%) sepia(35%) saturate(700%) hue-rotate(340deg)' 
                        : 'brightness(0) invert(1)',
                      transition: 'filter 0.25s ease'
                    }} 
                  />
                )}
              </div>
              <span 
                style={{
                  fontSize: '11px',
                  fontWeight: isActive ? 700 : 500,
                  color: isActive ? '#FFD489' : '#FFFFFF',
                  fontFamily: "'SF Pro Display', -apple-system, sans-serif",
                  letterSpacing: '0.2px',
                  transition: 'all 0.25s ease',
                  textShadow: isActive ? '0 1px 2px rgba(0,0,0,0.15)' : 'none'
                }}
              >
                {label}
              </span>
            </div>
          )}
        </NavLink>
      ))}
    </nav>
  );
};

export default BottomNav;