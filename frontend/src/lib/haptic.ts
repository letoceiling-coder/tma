/**
 * Telegram Haptic Feedback utilities
 * Provides haptic feedback for better user interaction experience
 */

type ImpactStyle = 'light' | 'medium' | 'heavy' | 'rigid' | 'soft';
type NotificationType = 'error' | 'success' | 'warning';

class HapticFeedback {
  private get telegram() {
    return typeof window !== 'undefined' ? (window as any).Telegram?.WebApp : null;
  }

  /**
   * Trigger impact haptic feedback
   * @param style - intensity of the haptic feedback
   */
  impact(style: ImpactStyle = 'medium') {
    try {
      this.telegram?.HapticFeedback?.impactOccurred(style);
    } catch (error) {
      console.debug('Haptic feedback not available');
    }
  }

  /**
   * Trigger notification haptic feedback
   * @param type - type of notification
   */
  notification(type: NotificationType) {
    try {
      this.telegram?.HapticFeedback?.notificationOccurred(type);
    } catch (error) {
      console.debug('Haptic feedback not available');
    }
  }

  /**
   * Trigger selection change haptic feedback
   * Light feedback for UI element selection
   */
  selection() {
    try {
      this.telegram?.HapticFeedback?.selectionChanged();
    } catch (error) {
      console.debug('Haptic feedback not available');
    }
  }

  // Convenience methods for common interactions
  
  /** Light tap - for buttons and links */
  lightTap() {
    this.impact('light');
  }

  /** Medium tap - for important buttons */
  mediumTap() {
    this.impact('medium');
  }

  /** Heavy tap - for primary actions */
  heavyTap() {
    this.impact('heavy');
  }

  /** Soft tap - for subtle interactions */
  softTap() {
    this.impact('soft');
  }

  /** Rigid tap - for confirmations */
  rigidTap() {
    this.impact('rigid');
  }

  /** Success feedback - for positive outcomes */
  success() {
    this.notification('success');
  }

  /** Error feedback - for errors */
  error() {
    this.notification('error');
  }

  /** Warning feedback - for warnings */
  warning() {
    this.notification('warning');
  }
}

export const haptic = new HapticFeedback();
