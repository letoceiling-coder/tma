import { useState } from "react";
import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route, useLocation } from "react-router-dom";
import { useEffect } from "react";
import Index from "./pages/Index";
import Friends from "./pages/Friends";
import Leaderboard from "./pages/Leaderboard";
import HowToPlay from "./pages/HowToPlay";
import NotFound from "./pages/NotFound";
import ChannelSubscriptionCheck from "./components/ChannelSubscriptionCheck";

const queryClient = new QueryClient();

// Каналы для проверки подписки (можно добавить несколько)
const CHANNEL_USERNAMES = ["bunny_world_2025", "Putin_tg_Russia"];

// Scroll to top on route change
const ScrollToTop = () => {
  const { pathname } = useLocation();
  
  useEffect(() => {
    window.scrollTo(0, 0);
    document.querySelectorAll('[data-scroll-container]').forEach((el) => {
      el.scrollTop = 0;
    });
  }, [pathname]);
  
  return null;
};

const AppContent = () => {
  const [isSubscribed, setIsSubscribed] = useState(() => {
    // Проверяем, была ли подписка подтверждена ранее в этой сессии
    return sessionStorage.getItem("channel_subscribed") === "true";
  });

  const handleSubscribed = () => {
    setIsSubscribed(true);
    sessionStorage.setItem("channel_subscribed", "true");
    // Сохраняем в localStorage для тестирования
    CHANNEL_USERNAMES.forEach((username) => {
      localStorage.setItem(`channel_subscribed_${username}`, "true");
    });
  };

  // Если пользователь не подписан, показываем экран подписки
  if (!isSubscribed) {
    return (
      <ChannelSubscriptionCheck
        channelUsernames={CHANNEL_USERNAMES}
        onSubscribed={handleSubscribed}
      />
    );
  }

  // Если подписан, показываем основное приложение
  return (
    <BrowserRouter>
      <ScrollToTop />
      <Routes>
        <Route path="/" element={<Index />} />
        <Route path="/friends" element={<Friends />} />
        <Route path="/leaderboard" element={<Leaderboard />} />
        <Route path="/how-to-play" element={<HowToPlay />} />
        {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
        <Route path="*" element={<NotFound />} />
      </Routes>
    </BrowserRouter>
  );
};

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <Toaster />
      <Sonner position="top-center" />
      <AppContent />
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;
