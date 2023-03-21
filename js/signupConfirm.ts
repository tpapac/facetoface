import { MM } from '@ionic-native/moodle-mobile';

export function goToCourse() {
    MM.util.goto('/course/view.php?id=2');
}