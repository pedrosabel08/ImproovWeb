const simpleGit = require('simple-git');
const fs = require('fs');

const git = simpleGit();

async function getTagsAndCommits() {
    try {
        // Obtém todas as tags
        const tags = await git.tags();

        const allCommits = [];

        // Lê os commits associados a cada tag
        for (const tag of tags.all) {
            const commits = await git.log({ from: tag });

            const filteredCommits = commits.all.filter(commit =>
                !commit.message.startsWith('backup')
            );

            allCommits.push({
                tag,
                commits: filteredCommits
            });
        }

        return allCommits;
    } catch (error) {
        console.error('Erro ao obter tags e commits:', error);
        return [];
    }
}

function saveToChangelog(commitsByTag) {
    const changelogContent = commitsByTag.map(tagInfo => {
        const tagSection = `## ${tagInfo.tag}\n`;
        const commitMessages = tagInfo.commits.map(commit => `- ${commit.message} (${commit.hash})`).join('\n');

        return `${tagSection}${commitMessages}`;
    }).join('\n\n');

    fs.writeFileSync('CHANGELOG.md', changelogContent);
    console.log('CHANGELOG.md atualizado com sucesso!');
}

(async () => {
    const commitsByTag = await getTagsAndCommits();

    if (commitsByTag.length > 0) {
        console.log('Tags e commits obtidos com sucesso!');
        console.log(commitsByTag);

        // Salva no CHANGELOG.md
        saveToChangelog(commitsByTag);
    }
})();
